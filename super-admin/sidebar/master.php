<?php
declare(strict_types=1);

/**
 * Super Admin sidebar master registry.
 *
 * The database remains the primary source. This registry provides:
 * - installation/fallback definitions
 * - stable menu keys, routes and Lucide icons
 * - route candidates for existing project pages
 * - active page-key aliases
 */

if (!function_exists('superAdminSidebarMasterOptions')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function superAdminSidebarMasterOptions(): array
    {
        return [
            [
                'id' => 11001,
                'parent_key' => null,
                'menu_key' => 'sa_dashboard',
                'title' => 'Dashboard',
                'icon' => 'layout-dashboard',
                'order' => 1,
                'routes' => [
                    'super-admin/dashboard.php',
                ],
                'page_keys' => [
                    'super_admin_dashboard',
                    'dashboard',
                ],
            ],
            [
                'id' => 11100,
                'parent_key' => null,
                'menu_key' => 'sa_school_management',
                'title' => 'School Management',
                'icon' => 'school',
                'order' => 2,
                'routes' => [
                    '#',
                ],
            ],
            [
                'id' => 11101,
                'parent_key' => 'sa_school_management',
                'menu_key' => 'sa_schools',
                'title' => 'School List',
                'icon' => 'list',
                'order' => 1,
                'routes' => [
                    'super-admin/schools.php',
                ],
                'page_keys' => [
                    'super_admin_schools',
                    'schools',
                    'school_list',
                ],
                'exclude_query_keys' => [
                    'action',
                    'view',
                ],
            ],
            [
                'id' => 11102,
                'parent_key' => 'sa_school_management',
                'menu_key' => 'sa_add_school',
                'title' => 'Add School',
                'icon' => 'school-plus',
                'order' => 2,
                'routes' => [
                    'super-admin/add-school.php',
                    'super-admin/school-create.php',
                    'super-admin/schools.php?action=create',
                    'super-admin/dashboard.php?module=add-school',
                ],
                'page_keys' => [
                    'super_admin_add_school',
                    'add_school',
                    'school_create',
                ],
            ],
            [
                'id' => 11103,
                'parent_key' => 'sa_school_management',
                'menu_key' => 'sa_school_requests',
                'title' => 'School Requests',
                'icon' => 'clipboard-list',
                'order' => 3,
                'routes' => [
                    'super-admin/school-requests.php',
                    'super-admin/schools.php?view=requests',
                    'super-admin/dashboard.php?module=school-requests',
                ],
                'page_keys' => [
                    'super_admin_school_requests',
                    'school_requests',
                ],
            ],
            [
                'id' => 11104,
                'parent_key' => 'sa_school_management',
                'menu_key' => 'sa_school_status',
                'title' => 'School Status',
                'icon' => 'badge-check',
                'order' => 4,
                'routes' => [
                    'super-admin/school-status.php',
                    'super-admin/schools.php?view=status',
                    'super-admin/dashboard.php?module=school-status',
                ],
                'page_keys' => [
                    'super_admin_school_status',
                    'school_status',
                ],
            ],
            [
                'id' => 11105,
                'parent_key' => 'sa_school_management',
                'menu_key' => 'sa_branches',
                'title' => 'Branches',
                'icon' => 'git-branch',
                'order' => 5,
                'routes' => [
                    'super-admin/branches.php',
                ],
                'page_keys' => [
                    'super_admin_branches',
                    'branches',
                ],
            ],
            [
                'id' => 11200,
                'parent_key' => null,
                'menu_key' => 'sa_users',
                'title' => 'User Management',
                'icon' => 'users-round',
                'order' => 3,
                'routes' => [
                    '#',
                ],
            ],
            [
                'id' => 11201,
                'parent_key' => 'sa_users',
                'menu_key' => 'sa_super_admin_users',
                'title' => 'Super Admin Users',
                'icon' => 'shield-user',
                'order' => 1,
                'routes' => [
                    'super-admin/super-admin-users.php',
                    'super-admin/users.php?type=super_admin',
                    'super-admin/users.php',
                ],
                'page_keys' => [
                    'super_admin_users',
                    'super_admin_user_list',
                ],
            ],
            [
                'id' => 11202,
                'parent_key' => 'sa_users',
                'menu_key' => 'sa_school_admin_users',
                'title' => 'School Admins',
                'icon' => 'user-cog',
                'order' => 2,
                'routes' => [
                    'super-admin/school-admins.php',
                    'super-admin/users.php?type=school_admin',
                    'super-admin/users.php',
                ],
                'page_keys' => [
                    'super_admin_school_admins',
                    'school_admins',
                ],
            ],
            [
                'id' => 11203,
                'parent_key' => 'sa_users',
                'menu_key' => 'sa_staff_users',
                'title' => 'Staff Users',
                'icon' => 'users',
                'order' => 3,
                'routes' => [
                    'super-admin/staff-users.php',
                    'super-admin/users.php?type=staff',
                    'super-admin/users.php',
                ],
                'page_keys' => [
                    'super_admin_staff_users',
                    'staff_users',
                ],
            ],
            [
                'id' => 11204,
                'parent_key' => 'sa_users',
                'menu_key' => 'sa_student_users',
                'title' => 'Students',
                'icon' => 'graduation-cap',
                'order' => 4,
                'routes' => [
                    'super-admin/students.php',
                    'super-admin/users.php?type=student',
                    'super-admin/users.php',
                ],
                'page_keys' => [
                    'super_admin_students',
                    'student_users',
                    'students',
                ],
            ],
            [
                'id' => 11205,
                'parent_key' => 'sa_users',
                'menu_key' => 'sa_parent_users',
                'title' => 'Parents',
                'icon' => 'users-round',
                'order' => 5,
                'routes' => [
                    'super-admin/parents.php',
                    'super-admin/users.php?type=parent',
                    'super-admin/users.php',
                ],
                'page_keys' => [
                    'super_admin_parents',
                    'parent_users',
                    'parents',
                ],
            ],
            [
                'id' => 11300,
                'parent_key' => null,
                'menu_key' => 'sa_roles',
                'title' => 'Roles & Permissions',
                'icon' => 'shield-check',
                'order' => 4,
                'routes' => [
                    'super-admin/roles.php',
                ],
                'page_keys' => [
                    'super_admin_roles',
                    'roles_permissions',
                    'roles',
                ],
            ],
            [
                'id' => 11400,
                'parent_key' => null,
                'menu_key' => 'sa_academic_management',
                'title' => 'Academic Management',
                'icon' => 'graduation-cap',
                'order' => 5,
                'routes' => [
                    '#',
                ],
            ],
            [
                'id' => 11401,
                'parent_key' => 'sa_academic_management',
                'menu_key' => 'sa_academic_years',
                'title' => 'Academic Years',
                'icon' => 'calendar-range',
                'order' => 1,
                'routes' => [
                    'super-admin/academic-years.php',
                    'academic-years.php',
                    'super-admin/dashboard.php?module=academic-years',
                ],
                'page_keys' => [
                    'super_admin_academic_years',
                    'academic_years',
                ],
            ],
            [
                'id' => 11402,
                'parent_key' => 'sa_academic_management',
                'menu_key' => 'sa_classes',
                'title' => 'Classes',
                'icon' => 'layout-grid',
                'order' => 2,
                'routes' => [
                    'super-admin/classes.php',
                    'classes.php',
                    'super-admin/dashboard.php?module=classes',
                ],
                'page_keys' => [
                    'super_admin_classes',
                    'classes',
                ],
            ],
            [
                'id' => 11403,
                'parent_key' => 'sa_academic_management',
                'menu_key' => 'sa_sections',
                'title' => 'Sections',
                'icon' => 'panels-top-left',
                'order' => 3,
                'routes' => [
                    'super-admin/sections.php',
                    'sections.php',
                    'super-admin/dashboard.php?module=sections',
                ],
                'page_keys' => [
                    'super_admin_sections',
                    'sections',
                ],
            ],
            [
                'id' => 11404,
                'parent_key' => 'sa_academic_management',
                'menu_key' => 'sa_subjects',
                'title' => 'Subjects',
                'icon' => 'book-open-text',
                'order' => 4,
                'routes' => [
                    'super-admin/subjects.php',
                    'subjects.php',
                    'super-admin/dashboard.php?module=subjects',
                ],
                'page_keys' => [
                    'super_admin_subjects',
                    'subjects',
                ],
            ],
            [
                'id' => 11500,
                'parent_key' => null,
                'menu_key' => 'sa_subscription_plans',
                'title' => 'Subscription & Plans',
                'icon' => 'credit-card',
                'order' => 6,
                'routes' => [
                    '#',
                ],
            ],
            [
                'id' => 11501,
                'parent_key' => 'sa_subscription_plans',
                'menu_key' => 'sa_plans',
                'title' => 'Plans',
                'icon' => 'badge-indian-rupee',
                'order' => 1,
                'routes' => [
                    'super-admin/plans.php',
                ],
                'page_keys' => [
                    'super_admin_plans',
                    'plans',
                ],
            ],
            [
                'id' => 11502,
                'parent_key' => 'sa_subscription_plans',
                'menu_key' => 'sa_subscriptions',
                'title' => 'School Subscriptions',
                'icon' => 'credit-card',
                'order' => 2,
                'routes' => [
                    'super-admin/subscriptions.php',
                ],
                'page_keys' => [
                    'super_admin_subscriptions',
                    'subscriptions',
                ],
                'exclude_query_keys' => [
                    'view',
                ],
            ],
            [
                'id' => 11503,
                'parent_key' => 'sa_subscription_plans',
                'menu_key' => 'sa_subscription_payments',
                'title' => 'Payments',
                'icon' => 'wallet-cards',
                'order' => 3,
                'routes' => [
                    'super-admin/subscription-payments.php',
                    'super-admin/subscriptions.php?view=payments',
                    'super-admin/dashboard.php?module=subscription-payments',
                ],
                'page_keys' => [
                    'super_admin_subscription_payments',
                    'subscription_payments',
                ],
            ],
            [
                'id' => 11504,
                'parent_key' => 'sa_subscription_plans',
                'menu_key' => 'sa_invoices',
                'title' => 'Invoices',
                'icon' => 'receipt-text',
                'order' => 4,
                'routes' => [
                    'super-admin/invoices.php',
                    'super-admin/subscriptions.php?view=invoices',
                    'super-admin/dashboard.php?module=invoices',
                ],
                'page_keys' => [
                    'super_admin_invoices',
                    'invoices',
                ],
            ],
            [
                'id' => 11600,
                'parent_key' => null,
                'menu_key' => 'sa_payment_management',
                'title' => 'Payment Management',
                'icon' => 'landmark',
                'order' => 7,
                'routes' => [
                    'super-admin/payments.php',
                    'super-admin/subscriptions.php?view=payments',
                    'super-admin/dashboard.php?module=payments',
                ],
                'page_keys' => [
                    'super_admin_payments',
                    'payment_management',
                    'payments',
                ],
            ],
            [
                'id' => 11700,
                'parent_key' => null,
                'menu_key' => 'sa_announcements',
                'title' => 'Announcements',
                'icon' => 'megaphone',
                'order' => 8,
                'routes' => [
                    'super-admin/announcements.php',
                    'super-admin/dashboard.php?module=announcements',
                ],
                'page_keys' => [
                    'super_admin_announcements',
                    'announcements',
                ],
            ],
            [
                'id' => 11800,
                'parent_key' => null,
                'menu_key' => 'sa_notifications',
                'title' => 'Notifications',
                'icon' => 'bell',
                'order' => 9,
                'routes' => [
                    'super-admin/notifications.php',
                    'super-admin/dashboard.php?module=notifications',
                ],
                'page_keys' => [
                    'super_admin_notifications',
                    'notifications',
                ],
            ],
            [
                'id' => 11900,
                'parent_key' => null,
                'menu_key' => 'sa_reports',
                'title' => 'Reports',
                'icon' => 'chart-no-axes-combined',
                'order' => 10,
                'routes' => [
                    'super-admin/reports.php',
                ],
                'page_keys' => [
                    'super_admin_reports',
                    'reports',
                ],
            ],
            [
                'id' => 12000,
                'parent_key' => null,
                'menu_key' => 'sa_settings',
                'title' => 'System Settings',
                'icon' => 'settings',
                'order' => 11,
                'routes' => [
                    '#',
                ],
            ],
            [
                'id' => 12001,
                'parent_key' => 'sa_settings',
                'menu_key' => 'sa_general_settings',
                'title' => 'General Settings',
                'icon' => 'sliders-horizontal',
                'order' => 1,
                'routes' => [
                    'super-admin/general-settings.php',
                    'super-admin/settings.php?section=general',
                    'super-admin/dashboard.php?module=general-settings',
                ],
                'page_keys' => [
                    'super_admin_general_settings',
                    'general_settings',
                ],
            ],
            [
                'id' => 12002,
                'parent_key' => 'sa_settings',
                'menu_key' => 'sa_email_settings',
                'title' => 'Email Settings',
                'icon' => 'mail-cog',
                'order' => 2,
                'routes' => [
                    'super-admin/email-settings.php',
                    'super-admin/settings.php?section=email',
                    'super-admin/dashboard.php?module=email-settings',
                ],
                'page_keys' => [
                    'super_admin_email_settings',
                    'email_settings',
                ],
            ],
            [
                'id' => 12003,
                'parent_key' => 'sa_settings',
                'menu_key' => 'sa_sms_settings',
                'title' => 'SMS Settings',
                'icon' => 'message-square-cog',
                'order' => 3,
                'routes' => [
                    'super-admin/sms-settings.php',
                    'super-admin/settings.php?section=sms',
                    'super-admin/dashboard.php?module=sms-settings',
                ],
                'page_keys' => [
                    'super_admin_sms_settings',
                    'sms_settings',
                ],
            ],
            [
                'id' => 12004,
                'parent_key' => 'sa_settings',
                'menu_key' => 'sa_backup_restore',
                'title' => 'Backup & Restore',
                'icon' => 'database-backup',
                'order' => 4,
                'routes' => [
                    'super-admin/backup-restore.php',
                    'super-admin/settings.php?section=backup',
                    'super-admin/dashboard.php?module=backup-restore',
                ],
                'page_keys' => [
                    'super_admin_backup_restore',
                    'backup_restore',
                ],
            ],
            [
                'id' => 12005,
                'parent_key' => 'sa_settings',
                'menu_key' => 'sa_database_settings',
                'title' => 'Database Settings',
                'icon' => 'database-cog',
                'order' => 5,
                'routes' => [
                    'super-admin/database-settings.php',
                    'super-admin/settings.php?section=database',
                    'super-admin/dashboard.php?module=database-settings',
                ],
                'page_keys' => [
                    'super_admin_database_settings',
                    'database_settings',
                ],
            ],
            [
                'id' => 12006,
                'parent_key' => 'sa_settings',
                'menu_key' => 'sa_modules',
                'title' => 'Modules',
                'icon' => 'boxes',
                'order' => 6,
                'routes' => [
                    'super-admin/modules.php',
                ],
                'page_keys' => [
                    'super_admin_modules',
                    'modules',
                ],
            ],
            [
                'id' => 12100,
                'parent_key' => null,
                'menu_key' => 'sa_theme_settings',
                'title' => 'Theme Settings',
                'icon' => 'palette',
                'order' => 12,
                'routes' => [
                    'super-admin/theme-settings.php',
                ],
                'page_keys' => [
                    'super_admin_theme_settings',
                    'theme_settings',
                ],
            ],
            [
                'id' => 12200,
                'parent_key' => null,
                'menu_key' => 'sa_sidebar_options',
                'title' => 'Sidebar Settings',
                'icon' => 'panel-left',
                'order' => 13,
                'routes' => [
                    '#',
                ],
            ],
            [
                'id' => 12201,
                'parent_key' => 'sa_sidebar_options',
                'menu_key' => 'sa_super_admin_sidebar_options',
                'title' => 'Super Admin Sidebar',
                'icon' => 'shield-check',
                'order' => 1,
                'routes' => [
                    'super-admin/sidebar-options.php',
                ],
                'page_keys' => [
                    'super_admin_sidebar_options',
                ],
            ],
            [
                'id' => 12202,
                'parent_key' => 'sa_sidebar_options',
                'menu_key' => 'sa_school_sidebar_options',
                'title' => 'School Admin Sidebar',
                'icon' => 'school',
                'order' => 2,
                'routes' => [
                    'sidebar-options.php',
                ],
                'page_keys' => [
                    'school_admin_sidebar_options',
                    'sidebar_options',
                ],
            ],
            [
                'id' => 12300,
                'parent_key' => null,
                'menu_key' => 'sa_activity_logs',
                'title' => 'Audit Logs',
                'icon' => 'history',
                'order' => 14,
                'routes' => [
                    'super-admin/activity-logs.php',
                ],
                'page_keys' => [
                    'super_admin_activity_logs',
                    'activity_logs',
                    'audit_logs',
                ],
            ],
            [
                'id' => 12400,
                'parent_key' => null,
                'menu_key' => 'sa_file_manager',
                'title' => 'File Manager',
                'icon' => 'folder-cog',
                'order' => 15,
                'routes' => [
                    'super-admin/file-manager.php',
                    'super-admin/dashboard.php?module=file-manager',
                ],
                'page_keys' => [
                    'super_admin_file_manager',
                    'file_manager',
                ],
            ],
            [
                'id' => 12500,
                'parent_key' => null,
                'menu_key' => 'sa_support_tickets',
                'title' => 'Support Tickets',
                'icon' => 'ticket-check',
                'order' => 16,
                'routes' => [
                    'super-admin/support-tickets.php',
                    'super-admin/dashboard.php?module=support-tickets',
                ],
                'page_keys' => [
                    'super_admin_support_tickets',
                    'support_tickets',
                ],
            ],
            [
                'id' => 12600,
                'parent_key' => null,
                'menu_key' => 'sa_profile',
                'title' => 'Profile',
                'icon' => 'circle-user-round',
                'order' => 17,
                'routes' => [
                    'super-admin/profile.php',
                    'profile.php',
                    'super-admin/dashboard.php?module=profile',
                ],
                'page_keys' => [
                    'super_admin_profile',
                    'profile',
                ],
            ],
            [
                'id' => 12700,
                'parent_key' => null,
                'menu_key' => 'sa_change_password',
                'title' => 'Change Password',
                'icon' => 'key-round',
                'order' => 18,
                'routes' => [
                    'super-admin/change-password.php',
                    'change-password.php',
                    'super-admin/dashboard.php?module=change-password',
                ],
                'page_keys' => [
                    'super_admin_change_password',
                    'change_password',
                ],
            ],
            [
                'id' => 12800,
                'parent_key' => null,
                'menu_key' => 'sa_logout',
                'title' => 'Logout',
                'icon' => 'log-out',
                'order' => 19,
                'routes' => [
                    'logout.php',
                ],
                'page_keys' => [
                    'logout',
                ],
            ],
        ];
    }
}

if (!function_exists('superAdminSidebarMasterFallback')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function superAdminSidebarMasterFallback(): array
    {
        $options = superAdminSidebarMasterOptions();
        $idsByKey = [];

        foreach ($options as $option) {
            $idsByKey[(string)$option['menu_key']] = (int)$option['id'];
        }

        $menus = [];

        foreach ($options as $option) {
            $parentKey = $option['parent_key'] ?? null;
            $routes = is_array($option['routes'] ?? null)
                ? $option['routes']
                : ['#'];

            $menus[] = [
                'id' => (int)$option['id'],
                'parent_id' => $parentKey !== null
                    ? ($idsByKey[(string)$parentKey] ?? null)
                    : null,
                'menu_key' => (string)$option['menu_key'],
                'display_title' => (string)$option['title'],
                'route' => (string)($routes[0] ?? '#'),
                'route_candidates' => $routes,
                'display_icon' => (string)$option['icon'],
                'badge_text' => null,
                'display_order' => (int)$option['order'],
                'page_keys' => $option['page_keys'] ?? [],
                'exclude_query_keys' =>
                    $option['exclude_query_keys'] ?? [],
            ];
        }

        return $menus;
    }
}

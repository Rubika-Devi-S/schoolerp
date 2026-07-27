<?php
declare(strict_types=1);

/**
 * Runtime-safe helpers for the School ERP layout.
 * All helpers return usable fallback values if optional tables/columns are unavailable.
 */

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('school_table_exists')) {
    function school_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table"
            );
            $stmt->execute(['table' => $table]);
            return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            error_log('school_table_exists: ' . $e->getMessage());
            return $cache[$table] = false;
        }
    }
}

if (!function_exists('school_column_exists')) {
    function school_column_exists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                   AND column_name = :column"
            );
            $stmt->execute([
                'table' => $table,
                'column' => $column,
            ]);
            return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            error_log('school_column_exists: ' . $e->getMessage());
            return $cache[$key] = false;
        }
    }
}

if (!function_exists('current_tenant_branding')) {
    function current_tenant_branding(): array
    {
        global $pdo;

        $defaults = [
            'school_name' => 'Brighton Public School',
            'tagline' => 'Nurturing Future Leaders',
            'logo_path' => '',
            'search_placeholder' => 'Search students, classes, teachers...',
            'sidebar_footer_title' => 'Excellence in Education',
            'sidebar_footer_text' => 'Building a strong foundation for a brighter tomorrow.',
            'sidebar_footer_icon' => 'graduation-cap',
        ];

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return $defaults;
        }

        $tenantId = max(1, (int)($_SESSION['tenant_id'] ?? 1));

        if (!school_table_exists($pdo, 'tenant_branding')) {
            return $defaults;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT school_name, tagline, logo_path, search_placeholder,
                        sidebar_footer_title, sidebar_footer_text, sidebar_footer_icon
                 FROM tenant_branding
                 WHERE tenant_id = :tenant_id
                   AND is_active = 1
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row)
                ? array_merge($defaults, array_filter($row, static fn($v) => $v !== null))
                : $defaults;
        } catch (Throwable $e) {
            error_log('current_tenant_branding: ' . $e->getMessage());
            return $defaults;
        }
    }
}

if (!function_exists('current_user')) {
    function current_user(): array
    {
        global $pdo;

        $defaults = [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'name' => (string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'John Admin'),
            'role_name' => (string)($_SESSION['role_name'] ?? 'Super Administrator'),
            'photo_path' => '',
            'notification_count' => 0,
            'message_count' => 0,
        ];

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return $defaults;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $tenantId = max(1, (int)($_SESSION['tenant_id'] ?? 1));

        if ($userId <= 0 || !school_table_exists($pdo, 'users')) {
            return $defaults;
        }

        try {
            $nameColumn = school_column_exists($pdo, 'users', 'name') ? 'u.name' : 'u.username AS name';
            $photoColumn = school_column_exists($pdo, 'users', 'photo_path') ? 'u.photo_path' : "'' AS photo_path";
            $roleJoin = school_table_exists($pdo, 'roles') && school_column_exists($pdo, 'users', 'role_id');
            $roleColumn = $roleJoin ? 'r.role_name' : "'" . addslashes($defaults['role_name']) . "' AS role_name";
            $joinSql = $roleJoin ? ' LEFT JOIN roles r ON r.id = u.role_id ' : '';

            $sql = "SELECT u.id, {$nameColumn}, {$photoColumn}, {$roleColumn}
                    FROM users u
                    {$joinSql}
                    WHERE u.id = :user_id";

            if (school_column_exists($pdo, 'users', 'tenant_id')) {
                $sql .= " AND u.tenant_id = :tenant_id";
            }
            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $params = ['user_id' => $userId];
            if (str_contains($sql, ':tenant_id')) {
                $params['tenant_id'] = $tenantId;
            }
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return $defaults;
            }

            if (school_table_exists($pdo, 'notifications')) {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM notifications
                         WHERE user_id = :user_id
                           AND tenant_id = :tenant_id
                           AND read_at IS NULL"
                    );
                    $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
                    $row['notification_count'] = (int)$stmt->fetchColumn();
                } catch (Throwable $e) {
                    $row['notification_count'] = 0;
                }
            }

            if (school_table_exists($pdo, 'user_messages')) {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM user_messages
                         WHERE receiver_user_id = :user_id
                           AND tenant_id = :tenant_id
                           AND read_at IS NULL"
                    );
                    $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
                    $row['message_count'] = (int)$stmt->fetchColumn();
                } catch (Throwable $e) {
                    $row['message_count'] = 0;
                }
            }

            return array_merge($defaults, $row);
        } catch (Throwable $e) {
            error_log('current_user: ' . $e->getMessage());
            return $defaults;
        }
    }
}

if (!function_exists('user_initials')) {
    function user_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));

        if (!$parts) {
            return 'U';
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }
}

if (!function_exists('get_accessible_academic_years')) {
    function get_accessible_academic_years(): array
    {
        global $pdo;

        $fallback = [[
            'id' => 1,
            'year_name' => '2024 - 2025',
            'is_current' => 1,
        ]];

        if (!isset($pdo) || !($pdo instanceof PDO) || !school_table_exists($pdo, 'academic_years')) {
            return $fallback;
        }

        try {
            $tenantId = max(1, (int)($_SESSION['tenant_id'] ?? 1));
            $sql = "SELECT id, year_name, is_current
                    FROM academic_years
                    WHERE status = 'active'";
            $params = [];

            if (school_column_exists($pdo, 'academic_years', 'tenant_id')) {
                $sql .= " AND tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }

            $sql .= " ORDER BY is_current DESC, id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) && $rows ? $rows : $fallback;
        } catch (Throwable $e) {
            error_log('get_accessible_academic_years: ' . $e->getMessage());
            return $fallback;
        }
    }
}

if (!function_exists('current_academic_year')) {
    function current_academic_year(): array
    {
        $years = get_accessible_academic_years();
        $selectedId = (int)($_SESSION['academic_year_id'] ?? 0);

        foreach ($years as $year) {
            if ($selectedId > 0 && (int)($year['id'] ?? 0) === $selectedId) {
                return $year;
            }
        }

        foreach ($years as $year) {
            if ((int)($year['is_current'] ?? 0) === 1) {
                $_SESSION['academic_year_id'] = (int)($year['id'] ?? 0);
                return $year;
            }
        }

        $first = is_array($years[0] ?? null) ? $years[0] : ['id' => 1, 'year_name' => '2024 - 2025'];
        $_SESSION['academic_year_id'] = (int)($first['id'] ?? 1);
        return $first;
    }
}

if (!function_exists('has_permission')) {
    function has_permission(string $pageKey, string $action = 'view'): bool
    {
        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return true;
        }

        $roleId = (int)($_SESSION['role_id'] ?? 0);
        if ($roleId <= 0) {
            return true;
        }

        if (!school_table_exists($pdo, 'app_pages') || !school_table_exists($pdo, 'role_page_permissions')) {
            return true;
        }

        $allowed = [
            'view' => 'can_view',
            'open' => 'can_open',
            'create' => 'can_create',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            'approve' => 'can_approve',
            'print' => 'can_print',
            'export' => 'can_export',
        ];

        $column = $allowed[$action] ?? 'can_view';
        if (!school_column_exists($pdo, 'role_page_permissions', $column)) {
            return true;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(MAX(rp.{$column}), 0)
                 FROM role_page_permissions rp
                 INNER JOIN app_pages p ON p.id = rp.page_id
                 WHERE rp.role_id = :role_id
                   AND p.page_key = :page_key
                   AND p.is_active = 1"
            );
            $stmt->execute(['role_id' => $roleId, 'page_key' => $pageKey]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            error_log('has_permission: ' . $e->getMessage());
            return true;
        }
    }
}

<?php
declare(strict_types=1);

/**
 * General Settings runtime helpers.
 *
 * This file contains no page output. It can be safely required by APIs,
 * controllers and layout files after the PDO connection is available.
 */

if (!function_exists('school_settings_table_exists')) {
    function school_settings_table_exists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name"
        );
        $statement->execute(['table_name' => $table]);
        return (int)$statement->fetchColumn() > 0;
    }
}

if (!function_exists('school_settings_column_exists')) {
    function school_settings_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name"
        );
        $statement->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return (int)$statement->fetchColumn() > 0;
    }
}

if (!function_exists('school_settings_index_exists')) {
    function school_settings_index_exists(PDO $pdo, string $table, string $index): bool
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND index_name = :index_name"
        );
        $statement->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);
        return (int)$statement->fetchColumn() > 0;
    }
}

if (!function_exists('school_settings_ensure_schema')) {
    function school_settings_ensure_schema(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'General Settings schema initialization must run before a database transaction starts.'
            );
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS school_general_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                academic_year_id BIGINT UNSIGNED NULL,
                school_start_time TIME NOT NULL DEFAULT '08:30:00',
                school_end_time TIME NOT NULL DEFAULT '16:00:00',
                date_format VARCHAR(20) NOT NULL DEFAULT 'd-m-Y',
                time_format VARCHAR(10) NOT NULL DEFAULT '12',
                language_code VARCHAR(10) NOT NULL DEFAULT 'en',
                currency_code VARCHAR(10) NOT NULL DEFAULT 'INR',
                admission_number_prefix VARCHAR(30) NOT NULL DEFAULT 'ADM',
                receipt_number_prefix VARCHAR(30) NOT NULL DEFAULT 'RCP',
                maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_school_general_settings_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $columns = [
            'academic_year_id' => 'BIGINT UNSIGNED NULL',
            'school_start_time' => "TIME NOT NULL DEFAULT '08:30:00'",
            'school_end_time' => "TIME NOT NULL DEFAULT '16:00:00'",
            'date_format' => "VARCHAR(20) NOT NULL DEFAULT 'd-m-Y'",
            'time_format' => "VARCHAR(10) NOT NULL DEFAULT '12'",
            'language_code' => "VARCHAR(10) NOT NULL DEFAULT 'en'",
            'currency_code' => "VARCHAR(10) NOT NULL DEFAULT 'INR'",
            'admission_number_prefix' => "VARCHAR(30) NOT NULL DEFAULT 'ADM'",
            'receipt_number_prefix' => "VARCHAR(30) NOT NULL DEFAULT 'RCP'",
            'maintenance_mode' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_by' => 'BIGINT UNSIGNED NULL',
            'updated_by' => 'BIGINT UNSIGNED NULL',
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($columns as $column => $definition) {
            if (!school_settings_column_exists($pdo, 'school_general_settings', $column)) {
                $pdo->exec(
                    "ALTER TABLE school_general_settings
                     ADD COLUMN `$column` $definition"
                );
            }
        }

        if (!school_settings_index_exists(
            $pdo,
            'school_general_settings',
            'uq_school_general_settings_tenant'
        )) {
            $pdo->exec(
                "ALTER TABLE school_general_settings
                 ADD UNIQUE KEY uq_school_general_settings_tenant (tenant_id)"
            );
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS school_number_sequences (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                sequence_type VARCHAR(30) NOT NULL,
                prefix VARCHAR(30) NOT NULL,
                last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
                number_width SMALLINT UNSIGNED NOT NULL DEFAULT 4,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_school_number_sequence (tenant_id, sequence_type, prefix),
                KEY idx_school_number_sequence_type (tenant_id, sequence_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS school_shift_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                shift_key VARCHAR(30) NOT NULL,
                shift_name VARCHAR(60) NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_school_shift_setting (tenant_id, shift_key),
                KEY idx_school_shift_enabled (tenant_id, is_enabled, display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('school_settings_defaults')) {
    function school_settings_defaults(): array
    {
        return [
            'id' => 0,
            'academic_year_id' => 0,
            'school_start_time' => '08:30:00',
            'school_end_time' => '16:00:00',
            'date_format' => 'd-m-Y',
            'time_format' => '12',
            'language_code' => 'en',
            'currency_code' => 'INR',
            'admission_number_prefix' => 'ADM',
            'receipt_number_prefix' => 'RCP',
            'maintenance_mode' => 0,
        ];
    }
}

if (!function_exists('school_settings_get')) {
    function school_settings_get(PDO $pdo, int $tenantId): array
    {
        $defaults = school_settings_defaults();
        if ($tenantId <= 0 || !school_settings_table_exists($pdo, 'school_general_settings')) {
            return $defaults;
        }

        $statement = $pdo->prepare(
            "SELECT *
             FROM school_general_settings
             WHERE tenant_id = :tenant_id
             LIMIT 1"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return array_merge($defaults, is_array($record) ? $record : []);
    }
}

if (!function_exists('school_settings_tenant_id')) {
    function school_settings_tenant_id(): int
    {
        $user = function_exists('current_user') ? current_user() : [];
        $user = is_array($user) ? $user : [];
        return (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        );
    }
}

if (!function_exists('school_settings_format_time')) {
    function school_settings_format_time(mixed $value, array $settings): string
    {
        $text = trim((string)$value);
        if ($text === '') return '';

        try {
            $time = new DateTimeImmutable($text);
        } catch (Throwable) {
            return $text;
        }

        return (string)($settings['time_format'] ?? '12') === '24'
            ? $time->format('H:i')
            : $time->format('h:i A');
    }
}

if (!function_exists('school_settings_format_date')) {
    function school_settings_format_date(mixed $value, array $settings): string
    {
        $text = trim((string)$value);
        if ($text === '') return '';

        try {
            $date = new DateTimeImmutable($text);
        } catch (Throwable) {
            return $text;
        }

        $format = (string)($settings['date_format'] ?? 'd-m-Y');
        if (!in_array($format, ['d-m-Y','d/m/Y','Y-m-d','m/d/Y'], true)) {
            $format = 'd-m-Y';
        }

        return $date->format($format);
    }
}

if (!function_exists('school_settings_format_datetime')) {
    function school_settings_format_datetime(mixed $value, array $settings): string
    {
        $text = trim((string)$value);
        if ($text === '') return '';

        try {
            $date = new DateTimeImmutable($text);
        } catch (Throwable) {
            return $text;
        }

        return school_settings_format_date($date->format('Y-m-d'), $settings)
            . ' '
            . school_settings_format_time($date->format('H:i:s'), $settings);
    }
}

if (!function_exists('school_settings_input_time')) {
    function school_settings_input_time(mixed $value): string
    {
        $text = trim((string)$value);
        if ($text === '') return '';
        return substr($text, 0, 5);
    }
}

if (!function_exists('school_settings_shift_definitions')) {
    function school_settings_shift_definitions(): array
    {
        return [
            'morning' => [
                'shift_key' => 'morning',
                'shift_name' => 'Morning',
                'display_order' => 10,
            ],
            'general' => [
                'shift_key' => 'general',
                'shift_name' => 'General',
                'display_order' => 20,
            ],
            'evening' => [
                'shift_key' => 'evening',
                'shift_name' => 'Evening',
                'display_order' => 30,
            ],
        ];
    }
}

if (!function_exists('school_settings_shift_settings')) {
    function school_settings_shift_settings(PDO $pdo, int $tenantId): array
    {
        $settings = school_settings_get($pdo, $tenantId);
        $definitions = school_settings_shift_definitions();

        $records = [];
        foreach ($definitions as $key => $definition) {
            $records[$key] = [
                ...$definition,
                'start_time' => $key === 'general'
                    ? (string)($settings['school_start_time'] ?? '08:30:00')
                    : '',
                'end_time' => $key === 'general'
                    ? (string)($settings['school_end_time'] ?? '16:00:00')
                    : '',
                'is_enabled' => $key === 'general' ? 1 : 0,
            ];
        }

        if (
            $tenantId <= 0
            || !school_settings_table_exists($pdo, 'school_shift_settings')
        ) {
            return array_values($records);
        }

        $statement = $pdo->prepare(
            "SELECT shift_key,shift_name,start_time,end_time,is_enabled,display_order
             FROM school_shift_settings
             WHERE tenant_id=:tenant_id
             ORDER BY display_order,id"
        );
        $statement->execute(['tenant_id' => $tenantId]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = strtolower(trim((string)($row['shift_key'] ?? '')));
            if (!isset($records[$key])) {
                continue;
            }

            $records[$key] = [
                ...$records[$key],
                'shift_name' => trim((string)($row['shift_name'] ?? ''))
                    ?: $records[$key]['shift_name'],
                'start_time' => (string)($row['start_time'] ?? ''),
                'end_time' => (string)($row['end_time'] ?? ''),
                'is_enabled' => (int)($row['is_enabled'] ?? 0) === 1 ? 1 : 0,
                'display_order' => (int)($row['display_order'] ?? $records[$key]['display_order']),
            ];
        }

        uasort(
            $records,
            static fn(array $left, array $right): int =>
                (int)$left['display_order'] <=> (int)$right['display_order']
        );

        return array_values($records);
    }
}

if (!function_exists('school_settings_save_shifts')) {
    function school_settings_save_shifts(
        PDO $pdo,
        int $tenantId,
        array $shifts,
        int $userId = 0
    ): void {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('School is required for Shift Time settings.');
        }

        if (!school_settings_table_exists($pdo, 'school_shift_settings')) {
            throw new RuntimeException('Shift Time settings table is not initialized.');
        }

        $definitions = school_settings_shift_definitions();
        $incoming = [];
        foreach ($shifts as $shift) {
            if (!is_array($shift)) continue;
            $key = strtolower(trim((string)($shift['shift_key'] ?? '')));
            if (isset($definitions[$key])) {
                $incoming[$key] = $shift;
            }
        }

        $statement = $pdo->prepare(
            "INSERT INTO school_shift_settings(
                tenant_id,shift_key,shift_name,start_time,end_time,
                is_enabled,display_order,created_by,updated_by
             ) VALUES(
                :tenant_id,:shift_key,:shift_name,:start_time,:end_time,
                :is_enabled,:display_order,:created_by,:updated_by
             )
             ON DUPLICATE KEY UPDATE
                shift_name=VALUES(shift_name),
                start_time=VALUES(start_time),
                end_time=VALUES(end_time),
                is_enabled=VALUES(is_enabled),
                display_order=VALUES(display_order),
                updated_by=VALUES(updated_by)"
        );

        foreach ($definitions as $key => $definition) {
            $row = $incoming[$key] ?? [];
            $enabled = (int)($row['is_enabled'] ?? 0) === 1 ? 1 : 0;
            $start = trim((string)($row['start_time'] ?? ''));
            $end = trim((string)($row['end_time'] ?? ''));

            $statement->execute([
                'tenant_id' => $tenantId,
                'shift_key' => $key,
                'shift_name' => $definition['shift_name'],
                'start_time' => $start !== '' ? $start : null,
                'end_time' => $end !== '' ? $end : null,
                'is_enabled' => $enabled,
                'display_order' => $definition['display_order'],
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);
        }
    }
}

if (!function_exists('school_settings_enabled_shifts')) {
    function school_settings_enabled_shifts(PDO $pdo, int $tenantId): array
    {
        $settings = school_settings_get($pdo, $tenantId);
        $result = [];

        foreach (school_settings_shift_settings($pdo, $tenantId) as $shift) {
            if ((int)($shift['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $start = trim((string)($shift['start_time'] ?? ''));
            $end = trim((string)($shift['end_time'] ?? ''));
            if ($start === '' || $end === '' || $start >= $end) {
                continue;
            }

            $name = trim((string)($shift['shift_name'] ?? ''));
            if ($name === '') continue;

            $result[] = [
                'shift_key' => (string)$shift['shift_key'],
                'shift_name' => $name,
                'start_time' => $start,
                'end_time' => $end,
                'display_order' => (int)($shift['display_order'] ?? 0),
                'display_label' => $name . ' ('
                    . school_settings_format_time($start, $settings)
                    . ' - '
                    . school_settings_format_time($end, $settings)
                    . ')',
            ];
        }

        usort(
            $result,
            static fn(array $left, array $right): int =>
                (int)$left['display_order'] <=> (int)$right['display_order']
        );

        return $result;
    }
}

if (!function_exists('school_settings_shift_allowed')) {
    function school_settings_shift_allowed(
        PDO $pdo,
        int $tenantId,
        string $shiftName
    ): ?array {
        $needle = mb_strtolower(trim($shiftName));
        if ($needle === '') return null;

        foreach (school_settings_enabled_shifts($pdo, $tenantId) as $shift) {
            if (mb_strtolower(trim((string)$shift['shift_name'])) === $needle) {
                return $shift;
            }
        }

        return null;
    }
}

if (!function_exists('school_settings_prefix')) {
    function school_settings_prefix(array $settings, string $sequenceType): string
    {
        $value = $sequenceType === 'receipt'
            ? (string)($settings['receipt_number_prefix'] ?? 'RCP')
            : (string)($settings['admission_number_prefix'] ?? 'ADM');

        $value = strtoupper(trim($value));
        return $value !== '' ? $value : ($sequenceType === 'receipt' ? 'RCP' : 'ADM');
    }
}

if (!function_exists('school_settings_existing_sequence_max')) {
    function school_settings_existing_sequence_max(
        PDO $pdo,
        int $tenantId,
        string $sequenceType,
        string $prefix
    ): int {
        $table = $sequenceType === 'receipt' ? 'fee_receipts' : 'students';
        $column = $sequenceType === 'receipt' ? 'receipt_no' : 'admission_no';

        if (!school_settings_table_exists($pdo, $table)
            || !school_settings_column_exists($pdo, $table, $column)) {
            return 0;
        }

        $prefixLength = strlen($prefix);
        $suffixStart = $prefixLength + 1;
        $sql = "SELECT MAX(CAST(SUBSTRING(`$column`, $suffixStart) AS UNSIGNED))
                FROM `$table`
                WHERE tenant_id = :tenant_id
                  AND LEFT(`$column`, $prefixLength) = :prefix
                  AND SUBSTRING(`$column`, $suffixStart) REGEXP '^[0-9]+$'";
        $statement = $pdo->prepare($sql);
        $statement->execute([
            'tenant_id' => $tenantId,
            'prefix' => $prefix,
        ]);

        return max(0, (int)($statement->fetchColumn() ?: 0));
    }
}

if (!function_exists('school_settings_number_exists')) {
    function school_settings_number_exists(
        PDO $pdo,
        int $tenantId,
        string $sequenceType,
        string $number
    ): bool {
        $table = $sequenceType === 'receipt' ? 'fee_receipts' : 'students';
        $column = $sequenceType === 'receipt' ? 'receipt_no' : 'admission_no';
        if (!school_settings_table_exists($pdo, $table)) return false;

        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM `$table`
             WHERE tenant_id = :tenant_id
               AND `$column` = :number"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'number' => $number,
        ]);
        return (int)$statement->fetchColumn() > 0;
    }
}

if (!function_exists('school_settings_format_sequence_number')) {
    function school_settings_format_sequence_number(string $prefix, int $number, int $width = 4): string
    {
        return $prefix . str_pad((string)$number, max(1, $width), '0', STR_PAD_LEFT);
    }
}

if (!function_exists('school_settings_peek_number')) {
    function school_settings_peek_number(
        PDO $pdo,
        int $tenantId,
        string $sequenceType,
        ?string $overridePrefix = null,
        int $width = 4
    ): string {
        $settings = school_settings_get($pdo, $tenantId);
        $prefix = $overridePrefix !== null && trim($overridePrefix) !== ''
            ? strtoupper(trim($overridePrefix))
            : school_settings_prefix($settings, $sequenceType);

        $existingMax = school_settings_existing_sequence_max(
            $pdo,
            $tenantId,
            $sequenceType,
            $prefix
        );

        $sequenceMax = 0;
        if (school_settings_table_exists($pdo, 'school_number_sequences')) {
            $statement = $pdo->prepare(
                "SELECT last_number, number_width
                 FROM school_number_sequences
                 WHERE tenant_id = :tenant_id
                   AND sequence_type = :sequence_type
                   AND prefix = :prefix
                 LIMIT 1"
            );
            $statement->execute([
                'tenant_id' => $tenantId,
                'sequence_type' => $sequenceType,
                'prefix' => $prefix,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $sequenceMax = (int)$row['last_number'];
                $width = max($width, (int)$row['number_width']);
            }
        }

        $next = max($existingMax, $sequenceMax) + 1;
        return school_settings_format_sequence_number($prefix, $next, $width);
    }
}

if (!function_exists('school_settings_next_number')) {
    function school_settings_next_number(
        PDO $pdo,
        int $tenantId,
        string $sequenceType,
        int $width = 4
    ): string {
        if (!in_array($sequenceType, ['admission','receipt'], true)) {
            throw new InvalidArgumentException('Unsupported school number sequence type.');
        }
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('School tenant is required for number generation.');
        }
        if (!school_settings_table_exists($pdo, 'school_number_sequences')) {
            throw new RuntimeException(
                'School number sequence table is not initialized. Open General Settings once or run the provided SQL.'
            );
        }

        $settings = school_settings_get($pdo, $tenantId);
        $prefix = school_settings_prefix($settings, $sequenceType);
        $existingMax = school_settings_existing_sequence_max(
            $pdo,
            $tenantId,
            $sequenceType,
            $prefix
        );

        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $insert = $pdo->prepare(
                "INSERT INTO school_number_sequences(
                    tenant_id, sequence_type, prefix, last_number, number_width
                 ) VALUES(
                    :tenant_id, :sequence_type, :prefix, :last_number, :number_width
                 )
                 ON DUPLICATE KEY UPDATE
                    number_width = GREATEST(number_width, VALUES(number_width))"
            );
            $insert->execute([
                'tenant_id' => $tenantId,
                'sequence_type' => $sequenceType,
                'prefix' => $prefix,
                'last_number' => $existingMax,
                'number_width' => $width,
            ]);

            $lock = $pdo->prepare(
                "SELECT id, last_number, number_width
                 FROM school_number_sequences
                 WHERE tenant_id = :tenant_id
                   AND sequence_type = :sequence_type
                   AND prefix = :prefix
                 FOR UPDATE"
            );
            $lock->execute([
                'tenant_id' => $tenantId,
                'sequence_type' => $sequenceType,
                'prefix' => $prefix,
            ]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Unable to lock school number sequence.');
            }

            $width = max($width, (int)$row['number_width']);
            $next = max((int)$row['last_number'], $existingMax) + 1;
            $number = school_settings_format_sequence_number($prefix, $next, $width);

            for ($attempt = 0; $attempt < 100 && school_settings_number_exists(
                $pdo,
                $tenantId,
                $sequenceType,
                $number
            ); $attempt++) {
                $next++;
                $number = school_settings_format_sequence_number($prefix, $next, $width);
            }

            if (school_settings_number_exists($pdo, $tenantId, $sequenceType, $number)) {
                throw new RuntimeException('Unable to generate a unique school sequence number.');
            }

            $update = $pdo->prepare(
                "UPDATE school_number_sequences
                 SET last_number = :last_number,
                     number_width = :number_width
                 WHERE id = :id"
            );
            $update->execute([
                'last_number' => $next,
                'number_width' => $width,
                'id' => (int)$row['id'],
            ]);

            if ($ownTransaction) {
                $pdo->commit();
            }

            return $number;
        } catch (Throwable $exception) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

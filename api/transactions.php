<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const TXN_BUILD = '2026-08-05-transactions-v3-unified-history';

function txOut(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function txInput(): array
{
    $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($type, 'application/json')) {
        $data = json_decode((string)file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function txScope(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    return [
        'tenant_id' => (int)($user['tenant_id'] ?? $user['school_id'] ?? $_SESSION['tenant_id'] ?? 0),
        'branch_id' => (int)($user['branch_id'] ?? $_SESSION['branch_id'] ?? 0),
        'user_id' => (int)($user['id'] ?? $user['user_id'] ?? $_SESSION['user_id'] ?? 0),
    ];
}

function txCsrf(array $input): void
{
    $session = (string)($_SESSION['transaction_csrf_token'] ?? '');
    $request = (string)($input['csrf_token'] ?? '');
    if ($session === '' || $request === '' || !hash_equals($session, $request)) {
        txOut(false, 'Invalid or expired CSRF token. Refresh the page.', [], 419);
    }
}

function txTable(PDO $pdo, string $table): bool
{
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name"
    );
    $q->execute(['table_name' => $table]);
    return (int)$q->fetchColumn() > 0;
}

function txColumn(PDO $pdo, string $table, string $column): bool
{
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:t AND column_name=:c");
    $q->execute(['t'=>$table,'c'=>$column]);
    return (int)$q->fetchColumn() > 0;
}

function txIndex(PDO $pdo, string $table, string $index): bool
{
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:t AND index_name=:i");
    $q->execute(['t'=>$table,'i'=>$index]);
    return (int)$q->fetchColumn() > 0;
}

function txEnsure(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS financial_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            account_name VARCHAR(150) NOT NULL,
            account_type ENUM('cash','bank','other') NOT NULL DEFAULT 'cash',
            opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_financial_account (tenant_id, account_name),
            KEY idx_financial_account_scope (tenant_id, branch_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS financial_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            transaction_id VARCHAR(70) NOT NULL,
            transaction_date DATE NOT NULL,
            transaction_type ENUM('fee_collection','salary','income','expense','refund') NOT NULL,
            category VARCHAR(150) NOT NULL,
            party_type ENUM('student','staff','vendor','other') NOT NULL DEFAULT 'other',
            party_id BIGINT UNSIGNED NULL,
            party_name VARCHAR(180) NOT NULL,
            payment_method ENUM('cash','bank','upi','cheque') NOT NULL,
            account_id BIGINT UNSIGNED NULL,
            account_name VARCHAR(150) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            status ENUM('completed','pending','cancelled','refunded') NOT NULL DEFAULT 'completed',
            reference_no VARCHAR(120) NULL,
            notes VARCHAR(500) NULL,
            source_module VARCHAR(80) NULL,
            source_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_transaction_id (tenant_id, transaction_id),
            KEY idx_transaction_filter (tenant_id, branch_id, transaction_date, transaction_type, status),
            KEY idx_transaction_account (tenant_id, account_id),
            KEY idx_transaction_party (tenant_id, party_type, party_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $typeQ = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='financial_transactions' AND column_name='transaction_type' LIMIT 1");
    $columnType = strtolower((string)$typeQ->fetchColumn());
    if (!str_contains($columnType, "'allowance'") || !str_contains($columnType, "'deduction'") || !str_contains($columnType, "'adjustment'")) {
        $pdo->exec("ALTER TABLE financial_transactions MODIFY transaction_type ENUM('fee_collection','salary','income','expense','refund','allowance','deduction','adjustment') NOT NULL");
    }
    if (!txColumn($pdo,'financial_transactions','direction')) {
        $pdo->exec("ALTER TABLE financial_transactions ADD COLUMN direction ENUM('in','out','neutral') NOT NULL DEFAULT 'in' AFTER transaction_type");
    }
    if (!txColumn($pdo,'financial_transactions','affects_balance')) {
        $pdo->exec("ALTER TABLE financial_transactions ADD COLUMN affects_balance TINYINT(1) NOT NULL DEFAULT 1 AFTER direction");
    }
    if (!txColumn($pdo,'financial_transactions','is_system_generated')) {
        $pdo->exec("ALTER TABLE financial_transactions ADD COLUMN is_system_generated TINYINT(1) NOT NULL DEFAULT 0 AFTER source_id");
    }
    if (!txIndex($pdo,'financial_transactions','uq_transaction_source')) {
        $pdo->exec("ALTER TABLE financial_transactions ADD UNIQUE KEY uq_transaction_source(tenant_id,source_module,source_id)");
    }
}

function txSeedAccounts(PDO $pdo, array $scope): void
{
    $accounts = [
        ['Main Cash', 'cash'],
        ['HDFC Bank', 'bank'],
        ['SBI Bank', 'bank'],
    ];

    $q = $pdo->prepare(
        "INSERT INTO financial_accounts
            (tenant_id, branch_id, account_name, account_type, opening_balance, status)
         VALUES (:tenant, :branch, :name, :type, 0, 'active')
         ON DUPLICATE KEY UPDATE status = 'active', account_type = VALUES(account_type)"
    );

    foreach ($accounts as [$name, $type]) {
        $q->execute([
            'tenant' => $scope['tenant_id'],
            'branch' => $scope['branch_id'] ?: null,
            'name' => $name,
            'type' => $type,
        ]);
    }
}

function txNumber(PDO $pdo, int $tenantId): string
{
    for ($i = 0; $i < 30; $i++) {
        $number = 'TRX-' . date('Y') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $q = $pdo->prepare(
            "SELECT COUNT(*) FROM financial_transactions
             WHERE tenant_id = :tenant AND transaction_id = :transaction_id"
        );
        $q->execute(['tenant' => $tenantId, 'transaction_id' => $number]);
        if ((int)$q->fetchColumn() === 0) {
            return $number;
        }
    }
    throw new RuntimeException('Unable to generate Transaction ID.');
}

function txSyncSources(PDO $pdo, array $scope): void
{
    $tenant = $scope['tenant_id'];
    $branch = $scope['branch_id'];

    $run = static function(string $sql, array $params) use ($pdo): void {
        try { $q=$pdo->prepare($sql); $q->execute($params); }
        catch (Throwable $e) { error_log('Transaction sync skipped: '.$e->getMessage()); }
    };
    $base = ['tenant'=>$tenant,'branch_scope'=>$branch,'branch'=>$branch];

    if (txTable($pdo,'fee_payments') && txTable($pdo,'fee_receipts')) {
        $studentJoin = txTable($pdo,'students') ? "LEFT JOIN students s ON s.id=r.student_id AND s.tenant_id=r.tenant_id" : "";
        $studentName = txTable($pdo,'students') ? "TRIM(CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,'')))" : "CONCAT('Student #',r.student_id)";
        $methodJoin = txTable($pdo,'payment_methods') ? "LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id" : "";
        $method = txTable($pdo,'payment_methods') ? "LOWER(CASE WHEN pm.method_key IN('cash','upi','cheque') THEN pm.method_key ELSE 'bank' END)" : "'cash'";
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_id,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT p.tenant_id,r.branch_id,CONCAT('FEE-',r.receipt_no,'-',p.id),DATE(p.paid_at),'fee_collection','in',1,'Fee Collection','student',r.student_id,$studentName,$method,
                   CASE WHEN $method='cash' THEN 'Main Cash' ELSE 'Bank Account' END,p.amount,
                   CASE p.status WHEN 'success' THEN 'completed' WHEN 'pending' THEN 'pending' WHEN 'reversed' THEN 'refunded' ELSE 'cancelled' END,
                   p.reference_no,CONCAT('Fee receipt ',r.receipt_no), 'fee_payment',p.id,1,r.collected_by
            FROM fee_payments p JOIN fee_receipts r ON r.id=p.receipt_id AND r.tenant_id=p.tenant_id $studentJoin $methodJoin
            WHERE p.tenant_id=:tenant AND (:branch_scope=0 OR r.branch_id=:branch)
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name),payment_method=VALUES(payment_method),reference_no=VALUES(reference_no)", $base);
    }

    if (txTable($pdo,'income_transactions')) {
        $purposeJoin = txTable($pdo,'income_purposes') ? "LEFT JOIN income_purposes ip ON ip.id=i.purpose_id AND ip.tenant_id=i.tenant_id" : "";
        $purpose = txTable($pdo,'income_purposes') ? "COALESCE(ip.purpose_name,'Income')" : "'Income'";
        $modeJoin = txTable($pdo,'payment_modes') ? "LEFT JOIN payment_modes pm ON pm.id=i.payment_mode_id" : "";
        $method = txTable($pdo,'payment_modes') ? "CASE WHEN LOWER(pm.method_name) LIKE '%cash%' THEN 'cash' WHEN LOWER(pm.method_name) LIKE '%upi%' THEN 'upi' WHEN LOWER(pm.method_name) LIKE '%cheque%' THEN 'cheque' ELSE 'bank' END" : "'cash'";
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_id,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT i.tenant_id,i.branch_id,i.income_no,i.income_date,
                   CASE WHEN i.source_module IN('fee_collection','fee_module') THEN 'fee_collection' ELSE 'income' END,'in',1,$purpose,
                   CASE WHEN i.student_id IS NULL THEN 'other' ELSE 'student' END,i.student_id,i.payer_name,$method,
                   CASE WHEN $method='cash' THEN 'Main Cash' ELSE 'Bank Account' END,i.amount,
                   CASE i.income_status WHEN 'received' THEN 'completed' WHEN 'pending' THEN 'pending' ELSE 'cancelled' END,
                   i.reference_no,i.description,'income_transaction',i.id,1,i.created_by
            FROM income_transactions i $purposeJoin $modeJoin
            WHERE i.tenant_id=:tenant AND (:branch_scope=0 OR i.branch_id=:branch)
              AND NOT (i.source_module IN('fee_collection','fee_module') AND i.receipt_id IS NOT NULL AND EXISTS(SELECT 1 FROM fee_payments fp WHERE fp.receipt_id=i.receipt_id AND fp.tenant_id=i.tenant_id))
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),transaction_type=VALUES(transaction_type),category=VALUES(category),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name),payment_method=VALUES(payment_method),notes=VALUES(notes)", $base);
    } elseif (txTable($pdo,'income_entries')) {
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_id,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT :tenant,NULL,e.income_no,e.income_date,'income','in',1,e.purpose,CASE WHEN e.student_id IS NULL THEN 'other' ELSE 'student' END,e.student_id,COALESCE(e.payer_source,'Income Source'),
                   CASE WHEN LOWER(e.payment_mode) LIKE '%cash%' THEN 'cash' WHEN LOWER(e.payment_mode) LIKE '%upi%' THEN 'upi' WHEN LOWER(e.payment_mode) LIKE '%cheque%' THEN 'cheque' ELSE 'bank' END,
                   CASE WHEN LOWER(e.payment_mode) LIKE '%cash%' THEN 'Main Cash' ELSE 'Bank Account' END,e.amount,
                   CASE LOWER(e.status) WHEN 'completed' THEN 'completed' WHEN 'pending' THEN 'pending' ELSE 'cancelled' END,e.reference_no,e.purpose,'income_entry',e.id,1,e.created_by
            FROM income_entries e
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name)", ['tenant'=>$tenant]);
    }

    $expenseTable = txTable($pdo,'expenses') ? 'expenses' : (txTable($pdo,'expense_transactions') ? 'expense_transactions' : '');
    if ($expenseTable==='expenses') {
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT e.tenant_id,e.branch_id,COALESCE(e.expense_no,CONCAT('EXP-',e.id)),e.expense_date,
                   CASE WHEN e.transaction_type='expense' THEN 'expense' WHEN e.transaction_type='refund' THEN 'refund' ELSE 'adjustment' END,
                   CASE WHEN e.transaction_type='expense' THEN 'out' ELSE 'in' END,1,COALESCE(e.purpose,'Expense'),'vendor',COALESCE(e.paid_to,e.vendor_name,'Vendor'),
                   CASE WHEN LOWER(e.payment_method) LIKE '%cash%' THEN 'cash' WHEN LOWER(e.payment_method) LIKE '%upi%' THEN 'upi' WHEN LOWER(e.payment_method) LIKE '%cheque%' THEN 'cheque' ELSE 'bank' END,
                   CASE WHEN LOWER(e.payment_method) LIKE '%cash%' THEN 'Main Cash' ELSE 'Bank Account' END,COALESCE(e.amount,0)+COALESCE(e.tax,0),
                   CASE LOWER(e.status) WHEN 'paid' THEN 'completed' WHEN 'pending' THEN 'pending' WHEN 'cancelled' THEN 'cancelled' ELSE 'completed' END,e.reference_no,e.description,'expense',e.id,1,e.created_by
            FROM expenses e WHERE e.tenant_id=:tenant AND (:branch_scope=0 OR e.branch_id=:branch)
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),transaction_type=VALUES(transaction_type),direction=VALUES(direction),category=VALUES(category),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name),notes=VALUES(notes)", $base);
    } elseif ($expenseTable==='expense_transactions') {
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT e.tenant_id,e.branch_id,COALESCE(e.expense_no,CONCAT('EXP-',e.id)),e.expense_date,'expense','out',1,COALESCE(e.purpose,'Expense'),'vendor',COALESCE(e.paid_to,'Vendor'),
                   CASE WHEN LOWER(e.payment_mode) LIKE '%cash%' THEN 'cash' WHEN LOWER(e.payment_mode) LIKE '%upi%' THEN 'upi' WHEN LOWER(e.payment_mode) LIKE '%cheque%' THEN 'cheque' ELSE 'bank' END,
                   CASE WHEN LOWER(e.payment_mode) LIKE '%cash%' THEN 'Main Cash' ELSE 'Bank Account' END,e.amount,
                   CASE LOWER(e.status) WHEN 'paid' THEN 'completed' WHEN 'pending' THEN 'pending' ELSE 'cancelled' END,e.reference_no,e.description,'expense_legacy',e.id,1,e.created_by
            FROM expense_transactions e WHERE e.tenant_id=:tenant AND (:branch_scope=0 OR e.branch_id=:branch)
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name),notes=VALUES(notes)", $base);
    }

    if (txTable($pdo,'staff_salary_transactions')) {
        $staffJoin = txTable($pdo,'staff_members') ? "LEFT JOIN staff_members sm ON sm.id=st.staff_id AND sm.tenant_id=st.tenant_id" : "";
        $staffName = txTable($pdo,'staff_members') ? "TRIM(CONCAT(COALESCE(sm.first_name,''),' ',COALESCE(sm.last_name,'')))" : "CONCAT('Staff #',st.staff_id)";
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_id,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT st.tenant_id,st.branch_id,st.payment_no,st.payment_date,'salary','out',1,'Salary Payment','staff',st.staff_id,$staffName,
                   CASE WHEN LOWER(st.payment_method)='cash' THEN 'cash' WHEN LOWER(st.payment_method)='upi' THEN 'upi' WHEN LOWER(st.payment_method)='cheque' THEN 'cheque' ELSE 'bank' END,
                   CASE WHEN LOWER(st.payment_method)='cash' THEN 'Main Cash' ELSE 'Bank Account' END,st.payment_amount,'completed',st.reference_no,st.remarks,'staff_salary_payment',st.id,1,st.created_by
            FROM staff_salary_transactions st $staffJoin WHERE st.tenant_id=:tenant AND (:branch_scope=0 OR st.branch_id=:branch)
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),amount=VALUES(amount),party_name=VALUES(party_name),payment_method=VALUES(payment_method),notes=VALUES(notes)", $base);
    } elseif (txTable($pdo,'salary_payments')) {
        $employeeJoin = txTable($pdo,'employees') ? "LEFT JOIN employees em ON em.id=sp.employee_id AND em.tenant_id=sp.tenant_id" : "";
        $employeeName = txTable($pdo,'employees') ? "COALESCE(em.name,CONCAT('Employee #',sp.employee_id))" : "CONCAT('Employee #',sp.employee_id)";
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_id,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT sp.tenant_id,sp.branch_id,sp.payment_no,sp.payment_date,'salary','out',1,'Salary Payment','staff',sp.employee_id,$employeeName,
                   CASE WHEN sp.payment_method='cash' THEN 'cash' WHEN sp.payment_method='upi' THEN 'upi' WHEN sp.payment_method='cheque' THEN 'cheque' ELSE 'bank' END,
                   CASE WHEN sp.payment_method='cash' THEN 'Main Cash' ELSE 'Bank Account' END,sp.amount,CASE sp.payment_status WHEN 'success' THEN 'completed' ELSE 'cancelled' END,sp.transaction_reference,sp.remarks,'salary_payment',sp.id,1,sp.created_by
            FROM salary_payments sp $employeeJoin WHERE sp.tenant_id=:tenant AND (:branch_scope=0 OR sp.branch_id=:branch)
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name),notes=VALUES(notes)", $base);
    }

    if (txTable($pdo,'staff_salary_ledger')) {
        $staffJoin = txTable($pdo,'staff_members') ? "LEFT JOIN staff_members sm ON sm.id=l.staff_id AND sm.tenant_id=l.tenant_id" : "";
        $staffName = txTable($pdo,'staff_members') ? "TRIM(CONCAT(COALESCE(sm.first_name,''),' ',COALESCE(sm.last_name,'')))" : "CONCAT('Staff #',l.staff_id)";
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_id,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT l.tenant_id,l.branch_id,CONCAT('SAL-LED-',l.id),DATE(l.transaction_at),
                   CASE WHEN l.entry_type LIKE 'allowance%' THEN 'allowance' WHEN l.entry_type LIKE 'deduction%' THEN 'deduction' ELSE 'adjustment' END,
                   'neutral',0,
                   CASE WHEN l.entry_type LIKE 'allowance%' THEN 'Salary Allowance' WHEN l.entry_type LIKE 'deduction%' THEN 'Salary Deduction' ELSE 'Salary Adjustment' END,
                   'staff',l.staff_id,$staffName,COALESCE(NULLIF(LOWER(l.payment_method),''),'cash'),'Salary Ledger',ABS(l.amount),'completed',l.reference_no,COALESCE(l.remarks,l.entry_type),'salary_ledger',l.id,1,l.created_by
            FROM staff_salary_ledger l $staffJoin
            WHERE l.tenant_id=:tenant AND (:branch_scope=0 OR l.branch_id=:branch)
              AND l.entry_type NOT IN('payment','salary_payment')
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),transaction_type=VALUES(transaction_type),category=VALUES(category),amount=VALUES(amount),party_name=VALUES(party_name),notes=VALUES(notes)", $base);
    }

    if (txTable($pdo,'fee_refund_account_entries') && txTable($pdo,'fee_refunds')) {
        $studentJoin = txTable($pdo,'students') ? "LEFT JOIN students s ON s.id=fr.student_id AND s.tenant_id=fr.tenant_id" : "";
        $studentName = txTable($pdo,'students') ? "TRIM(CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,'')))" : "CONCAT('Student #',fr.student_id)";
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_id,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT a.tenant_id,a.branch_id,CONCAT(fr.refund_no,'-',a.id),a.entry_date,'refund','out',CASE WHEN a.entry_type='refund_paid' THEN 1 ELSE 0 END,'Fee Refund','student',fr.student_id,$studentName,'bank','Bank Account',a.amount,
                   CASE WHEN a.entry_type='refund_paid' THEN 'refunded' ELSE 'pending' END,a.reference_no,COALESCE(a.remarks,fr.refund_reason),'fee_refund_entry',a.id,1,a.created_by
            FROM fee_refund_account_entries a JOIN fee_refunds fr ON fr.id=a.refund_id AND fr.tenant_id=a.tenant_id $studentJoin
            WHERE a.tenant_id=:tenant AND (:branch_scope=0 OR a.branch_id=:branch)
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),affects_balance=VALUES(affects_balance),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name),notes=VALUES(notes)", $base);
    }

    if (txTable($pdo,'transactions')) {
        $run("INSERT INTO financial_transactions
            (tenant_id,branch_id,transaction_id,transaction_date,transaction_type,direction,affects_balance,category,party_type,party_name,payment_method,account_name,amount,status,reference_no,notes,source_module,source_id,is_system_generated,created_by)
            SELECT x.tenant_id,x.branch_id,COALESCE(x.transaction_no,CONCAT('LEG-',x.id)),x.transaction_date,x.transaction_type,
                   CASE WHEN x.transaction_type='income' THEN 'in' ELSE 'out' END,1,COALESCE(x.category,'Other'),'other',COALESCE(x.party_name,'-'),
                   CASE WHEN LOWER(x.payment_method) LIKE '%cash%' THEN 'cash' WHEN LOWER(x.payment_method) LIKE '%upi%' THEN 'upi' WHEN LOWER(x.payment_method) LIKE '%cheque%' THEN 'cheque' ELSE 'bank' END,
                   CASE WHEN LOWER(x.payment_method) LIKE '%cash%' THEN 'Main Cash' ELSE 'Bank Account' END,x.amount,COALESCE(x.status,'pending'),x.reference_no,x.description,'legacy_transaction',x.id,1,x.created_by
            FROM transactions x WHERE x.tenant_id=:tenant AND (:branch_scope=0 OR x.branch_id=:branch)
            ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date),transaction_type=VALUES(transaction_type),direction=VALUES(direction),category=VALUES(category),amount=VALUES(amount),status=VALUES(status),party_name=VALUES(party_name),notes=VALUES(notes)", $base);
    }
}

function txMeta(PDO $pdo, array $scope): array
{
    $q = $pdo->prepare(
        "SELECT id, account_name, account_type, opening_balance
         FROM financial_accounts
         WHERE tenant_id = :tenant
           AND (:branch_scope = 0 OR branch_id = :branch OR branch_id IS NULL)
           AND status = 'active'
         ORDER BY account_type, account_name"
    );
    $q->execute([
        'tenant' => $scope['tenant_id'],
        'branch_scope' => $scope['branch_id'],
        'branch' => $scope['branch_id'],
    ]);

    return [
        'transaction_types' => [
            ['value' => 'fee_collection', 'label' => 'Fee Collection'],
            ['value' => 'salary', 'label' => 'Salary'],
            ['value' => 'income', 'label' => 'Income'],
            ['value' => 'expense', 'label' => 'Expense'],
            ['value' => 'refund', 'label' => 'Refund'],
            ['value' => 'allowance', 'label' => 'Allowance'],
            ['value' => 'deduction', 'label' => 'Deduction'],
            ['value' => 'adjustment', 'label' => 'Adjustment'],
        ],
        'payment_methods' => [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank', 'label' => 'Bank'],
            ['value' => 'upi', 'label' => 'UPI'],
            ['value' => 'cheque', 'label' => 'Cheque'],
        ],
        'statuses' => [
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
            ['value' => 'refunded', 'label' => 'Refunded'],
        ],
        'party_types' => [
            ['value' => 'student', 'label' => 'Student'],
            ['value' => 'staff', 'label' => 'Staff'],
            ['value' => 'vendor', 'label' => 'Vendor'],
            ['value' => 'other', 'label' => 'Other'],
        ],
        'categories' => [
            'Tuition Fee', 'Transport Fee', 'Admission Fee', 'Exam Fee',
            'Salary', 'Allowance', 'Donation', 'Interest Income',
            'Office Expense', 'Maintenance', 'Stationery', 'Electricity',
            'Water', 'Internet', 'Medical', 'Refund', 'Salary Payment', 'Salary Allowance', 'Salary Deduction', 'Salary Adjustment', 'Other'
        ],
        'accounts' => $q->fetchAll(PDO::FETCH_ASSOC),
        'csrf_token' => $_SESSION['transaction_csrf_token'],
        'api_build' => TXN_BUILD,
    ];
}

function txWhere(array $scope, array $filter, array &$params): array
{
    $where = [
        't.tenant_id = :tenant',
        '(:branch_scope = 0 OR t.branch_id = :branch)'
    ];
    $params = [
        'tenant' => $scope['tenant_id'],
        'branch_scope' => $scope['branch_id'],
        'branch' => $scope['branch_id'],
    ];

    $search = trim((string)($filter['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            t.transaction_id LIKE :search OR
            t.category LIKE :search OR
            t.party_name LIKE :search OR
            t.account_name LIKE :search OR
            t.reference_no LIKE :search OR
            t.notes LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    if (!isset($filter['transaction_type']) && isset($filter['type'])) {
        $filter['transaction_type'] = $filter['type'];
    }
    if (!isset($filter['from_date']) && isset($filter['date_from'])) {
        $filter['from_date'] = $filter['date_from'];
    }
    if (!isset($filter['to_date']) && isset($filter['date_to'])) {
        $filter['to_date'] = $filter['date_to'];
    }

    foreach (['transaction_type', 'payment_method', 'status'] as $key) {
        $value = trim((string)($filter[$key] ?? 'all'));
        if ($value !== '' && $value !== 'all') {
            $where[] = "t.$key = :$key";
            $params[$key] = $value;
        }
    }

    $category = trim((string)($filter['category'] ?? 'all'));
    if ($category !== '' && $category !== 'all') {
        $where[] = 't.category = :category';
        $params['category'] = $category;
    }

    if (!empty($filter['from_date'])) {
        $where[] = 't.transaction_date >= :from_date';
        $params['from_date'] = $filter['from_date'];
    }
    if (!empty($filter['to_date'])) {
        $where[] = 't.transaction_date <= :to_date';
        $params['to_date'] = $filter['to_date'];
    }

    return $where;
}

function txRows(PDO $pdo, array $scope, array $filter, int $page, int $perPage): array
{
    $params = [];
    $where = txWhere($scope, $filter, $params);
    $whereSql = implode(' AND ', $where);

    $q = $pdo->prepare("SELECT COUNT(*) FROM financial_transactions t WHERE $whereSql");
    $q->execute($params);
    $total = (int)$q->fetchColumn();

    $lastPage = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, $page), $lastPage);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT t.*, t.transaction_id transaction_no, t.notes description, u.name created_by_name, b.branch_name,
                CASE
                    WHEN t.affects_balance=0 THEN 0
                    WHEN t.direction='in' THEN t.amount
                    WHEN t.direction='out' THEN -t.amount
                    ELSE 0
                END signed_amount
            FROM financial_transactions t
            LEFT JOIN users u ON u.id = t.created_by
            LEFT JOIN branches b ON b.id = t.branch_id
            WHERE $whereSql
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT :offset, :per_page";

    $q = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $q->bindValue(':' . $key, $value);
    }
    $q->bindValue(':offset', $offset, PDO::PARAM_INT);
    $q->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $q->execute();
    $records = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as &$record) {
        $record['can_edit'] = (int)($record['is_system_generated'] ?? 0) === 0;
        $record['can_delete'] = $record['can_edit'];
    }
    unset($record);

    $statsSql = "SELECT
        COALESCE(SUM(CASE WHEN status='completed' AND affects_balance=1 AND direction='in' AND transaction_type='income' THEN amount ELSE 0 END),0) total_income,
        COALESCE(SUM(CASE WHEN status='completed' AND affects_balance=1 AND direction='out' AND transaction_type='expense' THEN amount ELSE 0 END),0) total_expense,
        COALESCE(SUM(CASE WHEN status='completed' AND affects_balance=1 AND direction='in' AND transaction_type='fee_collection' THEN amount ELSE 0 END),0) total_fee_collection,
        COALESCE(SUM(CASE WHEN status='completed' AND affects_balance=1 AND direction='out' AND transaction_type='salary' THEN amount ELSE 0 END),0) total_salary_paid
        FROM financial_transactions t WHERE $whereSql";
    $q = $pdo->prepare($statsSql);
    $q->execute($params);
    $stats = $q->fetch(PDO::FETCH_ASSOC) ?: [];

    $balanceQ = $pdo->prepare(
        "SELECT
            a.account_type,
            COALESCE(SUM(a.opening_balance),0) + COALESCE(SUM(
                CASE
                    WHEN t.status = 'completed' AND t.transaction_type IN ('fee_collection','income') THEN t.amount
                    WHEN t.status = 'completed' AND t.transaction_type IN ('salary','expense','refund') THEN -t.amount
                    ELSE 0
                END
            ),0) balance
         FROM financial_accounts a
         LEFT JOIN financial_transactions t
           ON t.account_id = a.id
          AND t.tenant_id = a.tenant_id
          AND (:branch_scope = 0 OR t.branch_id = :branch)
         WHERE a.tenant_id = :tenant
           AND (:branch_scope2 = 0 OR a.branch_id = :branch2 OR a.branch_id IS NULL)
           AND a.status = 'active'
         GROUP BY a.account_type"
    );
    $balanceQ->execute([
        'tenant' => $scope['tenant_id'],
        'branch_scope' => $scope['branch_id'],
        'branch' => $scope['branch_id'],
        'branch_scope2' => $scope['branch_id'],
        'branch2' => $scope['branch_id'],
    ]);

    $stats['cash_balance'] = 0;
    $stats['bank_balance'] = 0;
    foreach ($balanceQ->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['account_type'] === 'cash') {
            $stats['cash_balance'] += (float)$row['balance'];
        }
        if ($row['account_type'] === 'bank') {
            $stats['bank_balance'] += (float)$row['balance'];
        }
    }

    $summaryQ = $pdo->prepare("SELECT category,COUNT(*) transaction_count,
            COALESCE(SUM(CASE WHEN status='completed' AND affects_balance=1 AND direction='in' THEN amount ELSE 0 END),0) total_income,
            COALESCE(SUM(CASE WHEN status='completed' AND affects_balance=1 AND direction='out' THEN amount ELSE 0 END),0) total_expense
        FROM financial_transactions t WHERE $whereSql GROUP BY category ORDER BY category");
    $summaryQ->execute($params);
    $summary = $summaryQ->fetchAll(PDO::FETCH_ASSOC);

    return [
        'records' => $records,
        'stats' => $stats,
        'summary' => $summary,
        'categories' => txMeta($pdo, $scope)['categories'],
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ],
    ];
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    txOut(false, 'Database connection missing.', [], 500);
}

$scope = txScope();
if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    txOut(false, 'Tenant or user session is missing.', [], 401);
}

if (empty($_SESSION['transaction_csrf_token']) || !is_string($_SESSION['transaction_csrf_token'])) {
    $_SESSION['transaction_csrf_token'] = bin2hex(random_bytes(32));
}

$input = txInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));

try {
    txEnsure($pdo);
    txSeedAccounts($pdo, $scope);
    txSyncSources($pdo, $scope);

    if ($action === 'meta') {
        txOut(true, 'Transaction metadata loaded.', txMeta($pdo, $scope));
    }

    if ($action === 'list') {
        $page = max(1, (int)($_GET['page'] ?? $input['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? $input['per_page'] ?? 10)));
        $data = txRows($pdo, $scope, array_merge($_GET, $input), $page, $perPage);
        $data['meta'] = txMeta($pdo, $scope);
        $data['permissions'] = ['create' => true, 'edit' => true, 'delete' => true, 'view' => true, 'print' => true];
        txOut(true, 'Transactions loaded.', $data);
    }

    if ($action === 'save') {
        txCsrf($input);

        $id = (int)($input['id'] ?? 0);
        $date = trim((string)($input['transaction_date'] ?? ''));
        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Transaction Date is invalid.');
        }

        $type = strtolower(trim((string)($input['transaction_type'] ?? '')));
        if (!in_array($type, ['fee_collection', 'salary', 'income', 'expense', 'refund', 'allowance', 'deduction', 'adjustment'], true)) {
            throw new InvalidArgumentException('Transaction Type is invalid.');
        }

        $category = trim((string)($input['category'] ?? ''));
        $partyType = strtolower(trim((string)($input['party_type'] ?? 'other')));
        $partyName = trim((string)($input['party_name'] ?? ''));
        $paymentMethod = strtolower(trim((string)($input['payment_method'] ?? '')));
        $accountId = (int)($input['account_id'] ?? 0);
        $amount = round((float)($input['amount'] ?? 0), 2);
        $status = strtolower(trim((string)($input['status'] ?? 'completed')));

        if ($category === '') throw new InvalidArgumentException('Category is required.');
        if (!in_array($partyType, ['student', 'staff', 'vendor', 'other'], true)) throw new InvalidArgumentException('Party Type is invalid.');
        if ($partyName === '') throw new InvalidArgumentException('Student / Staff / Vendor Name is required.');
        if (!in_array($paymentMethod, ['cash', 'bank', 'upi', 'cheque'], true)) throw new InvalidArgumentException('Payment Method is invalid.');
        if ($amount <= 0) throw new InvalidArgumentException('Amount must be greater than zero.');
        if (!in_array($status, ['completed', 'pending', 'cancelled', 'refunded'], true)) throw new InvalidArgumentException('Status is invalid.');

        if ($accountId <= 0) {
            $preferredType = $paymentMethod === 'cash' ? 'cash' : 'bank';
            $fallbackQ = $pdo->prepare(
                "SELECT id FROM financial_accounts
                 WHERE tenant_id=:tenant
                   AND (:branch_scope=0 OR branch_id=:branch OR branch_id IS NULL)
                   AND account_type=:account_type
                   AND status='active'
                 ORDER BY branch_id IS NULL, id
                 LIMIT 1"
            );
            $fallbackQ->execute([
                'tenant'=>$scope['tenant_id'],
                'branch_scope'=>$scope['branch_id'],
                'branch'=>$scope['branch_id'],
                'account_type'=>$preferredType,
            ]);
            $accountId = (int)$fallbackQ->fetchColumn();
        }

        $accountQ = $pdo->prepare(
            "SELECT id, account_name FROM financial_accounts
             WHERE id = :id AND tenant_id = :tenant AND status = 'active' LIMIT 1"
        );
        $accountQ->execute(['id' => $accountId, 'tenant' => $scope['tenant_id']]);
        $account = $accountQ->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new InvalidArgumentException('Select a valid Account.');

        $params = [
            'transaction_date' => $date,
            'transaction_type' => $type,
            'direction' => in_array($type,['fee_collection','income'],true) ? 'in' : (in_array($type,['allowance','deduction','adjustment'],true) ? 'neutral' : 'out'),
            'affects_balance' => in_array($type,['allowance','deduction','adjustment'],true) ? 0 : 1,
            'category' => $category,
            'party_type' => $partyType,
            'party_id' => (int)($input['party_id'] ?? 0) ?: null,
            'party_name' => $partyName,
            'payment_method' => $paymentMethod,
            'account_id' => (int)$account['id'],
            'account_name' => $account['account_name'],
            'amount' => $amount,
            'status' => $status,
            'reference_no' => trim((string)($input['reference_no'] ?? '')) ?: null,
            'notes' => trim((string)($input['notes'] ?? $input['description'] ?? '')) ?: null,
            'source_module' => trim((string)($input['source_module'] ?? 'manual')) ?: 'manual',
            'updated_by' => $scope['user_id'],
            'tenant' => $scope['tenant_id'],
        ];

        if ($id > 0) {
            $params['id'] = $id;
            $q = $pdo->prepare(
                "UPDATE financial_transactions SET
                    transaction_date = :transaction_date,
                    transaction_type = :transaction_type,
                    direction = :direction,
                    affects_balance = :affects_balance,
                    category = :category,
                    party_type = :party_type,
                    party_id = :party_id,
                    party_name = :party_name,
                    payment_method = :payment_method,
                    account_id = :account_id,
                    account_name = :account_name,
                    amount = :amount,
                    status = :status,
                    reference_no = :reference_no,
                    notes = :notes,
                    source_module = :source_module,
                    updated_by = :updated_by
                 WHERE id = :id AND tenant_id = :tenant"
            );
            $q->execute($params);
            txOut(true, 'Transaction updated successfully.');
        }

        $params['branch'] = $scope['branch_id'] ?: null;
        $params['transaction_id'] = txNumber($pdo, $scope['tenant_id']);
        $params['created_by'] = $scope['user_id'];

        $q = $pdo->prepare(
            "INSERT INTO financial_transactions (
                tenant_id, branch_id, transaction_id, transaction_date,
                transaction_type, direction, affects_balance, category, party_type, party_id, party_name,
                payment_method, account_id, account_name, amount, status,
                reference_no, notes, source_module, created_by, updated_by
             ) VALUES (
                :tenant, :branch, :transaction_id, :transaction_date,
                :transaction_type, :direction, :affects_balance, :category, :party_type, :party_id, :party_name,
                :payment_method, :account_id, :account_name, :amount, :status,
                :reference_no, :notes, :source_module, :created_by, :updated_by
             )"
        );
        $q->execute($params);

        txOut(true, 'Transaction created successfully.', ['transaction_id' => $params['transaction_id']]);
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        $q = $pdo->prepare(
            "SELECT t.*, t.transaction_id transaction_no, t.notes description, u.name created_by_name, b.branch_name
             FROM financial_transactions t
             LEFT JOIN users u ON u.id = t.created_by
             LEFT JOIN branches b ON b.id = t.branch_id
             WHERE t.id = :id AND t.tenant_id = :tenant LIMIT 1"
        );
        $q->execute(['id' => $id, 'tenant' => $scope['tenant_id']]);
        $record = $q->fetch(PDO::FETCH_ASSOC);
        if (!$record) throw new InvalidArgumentException('Transaction record not found.');
        txOut(true, 'Transaction detail loaded.', ['record' => $record]);
    }

    if ($action === 'delete') {
        txCsrf($input);
        $id = (int)($input['id'] ?? 0);
        $q = $pdo->prepare(
            "DELETE FROM financial_transactions WHERE id = :id AND tenant_id = :tenant AND is_system_generated=0"
        );
        $q->execute(['id' => $id, 'tenant' => $scope['tenant_id']]);
        if ($q->rowCount() === 0) throw new InvalidArgumentException('Transaction record not found.');
        txOut(true, 'Transaction deleted successfully.');
    }

    if ($action === 'update_status') {
        txCsrf($input);
        $id = (int)($input['id'] ?? 0);
        $status = strtolower(trim((string)($input['status'] ?? '')));
        if (!in_array($status, ['completed','pending','cancelled','refunded'], true)) {
            throw new InvalidArgumentException('Status is invalid.');
        }
        $q = $pdo->prepare(
            "UPDATE financial_transactions
             SET status=:status, updated_by=:updated_by
             WHERE id=:id AND tenant_id=:tenant"
        );
        $q->execute([
            'status'=>$status,
            'updated_by'=>$scope['user_id'],
            'id'=>$id,
            'tenant'=>$scope['tenant_id'],
        ]);
        if ($q->rowCount() === 0) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM financial_transactions WHERE id=:id AND tenant_id=:tenant");
            $check->execute(['id'=>$id,'tenant'=>$scope['tenant_id']]);
            if ((int)$check->fetchColumn() === 0) {
                throw new InvalidArgumentException('Transaction record not found.');
            }
        }
        txOut(true, 'Transaction status updated successfully.');
    }

    if ($action === 'export' || $action === 'print') {
        $data = txRows($pdo, $scope, array_merge($_GET, $input), 1, 5000);
        $records = $data['records'];

        if ($action === 'export') {
            while (ob_get_level() > 0) ob_end_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="transactions-' . date('Ymd-His') . '.csv"');
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Transaction ID', 'Date', 'Transaction Type', 'Category',
                'Student / Staff / Vendor Name', 'Payment Method', 'Account',
                'Amount', 'Status', 'Reference No', 'Notes'
            ]);
            foreach ($records as $row) {
                fputcsv($output, [
                    $row['transaction_id'], $row['transaction_date'], $row['transaction_type'],
                    $row['category'], $row['party_name'], $row['payment_method'],
                    $row['account_name'], $row['amount'], $row['status'],
                    $row['reference_no'], $row['notes']
                ]);
            }
            fclose($output);
            exit;
        }

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Transactions Report</title>';
        echo '<style>body{font-family:Arial,sans-serif;padding:24px;color:#111827}h2{margin:0 0 16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:7px;font-size:11px}th{background:#f3f4f6;text-align:left}.right{text-align:right}@media print{button{display:none}}</style></head><body>';
        echo '<button onclick="window.print()">Print</button><h2>All Transactions</h2><table><thead><tr><th>Transaction ID</th><th>Date</th><th>Type</th><th>Category</th><th>Name</th><th>Method</th><th>Account</th><th class="right">Amount</th><th>Status</th><th>Notes</th></tr></thead><tbody>';
        foreach ($records as $row) {
            echo '<tr>';
            foreach (['transaction_id','transaction_date','transaction_type','category','party_name','payment_method','account_name'] as $field) {
                echo '<td>' . htmlspecialchars((string)$row[$field]) . '</td>';
            }
            echo '<td class="right">₹' . number_format((float)$row['amount'], 2) . '</td>';
            echo '<td>' . htmlspecialchars((string)$row['status']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$row['notes']) . '</td></tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    txOut(false, 'Invalid Transaction action.', [], 400);
} catch (InvalidArgumentException $e) {
    txOut(false, $e->getMessage(), [], 422);
} catch (Throwable $e) {
    error_log('transactions.php build=' . TXN_BUILD . ' action=' . $action . ' line=' . $e->getLine() . ' error=' . $e->getMessage());
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    txOut(
        false,
        (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))
            ? 'Transaction request failed [' . TXN_BUILD . ']: ' . $e->getMessage()
            : 'Unable to complete Transaction request.',
        [],
        500
    );
}

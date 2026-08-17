<?php
declare(strict_types=1);

/*
 * Standalone Fee Receipt Print
 * Correct calculation method:
 *   Due Amount    = Original fee - earlier paid - earlier discount - current discount
 *   Paid Amount   = amount allocated in this receipt
 *   Balance Amount= Due Amount - Paid Amount
 *
 * This file does NOT use student_fee_assignments.balance_amount for printing.
 * That avoids cumulative/duplicate schedule values from making the receipt totals mismatch.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function frTable(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $s = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name"
    );
    $s->execute(['table_name' => $table]);
    return $cache[$table] = (int)$s->fetchColumn() > 0;
}

function frScope(): array
{
    $u = function_exists('current_user') ? current_user() : [];
    $u = is_array($u) ? $u : [];
    return [
        'tenant_id' => (int)($u['tenant_id'] ?? $u['school_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0),
        'branch_id' => (int)($u['branch_id'] ?? $_SESSION['branch_id'] ?? 0),
        'user_id' => (int)($u['id'] ?? $u['user_id'] ?? $_SESSION['user_id'] ?? 0),
    ];
}

function frFail(string $message, int $status = 400): never
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><body style="font-family:Arial;padding:30px"><h3>Unable to print receipt</h3><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p></body></html>';
    exit;
}

function frReceipt(PDO $pdo, array $scope, int $id): array
{
    $nameSql = "TRIM(CONCAT(COALESCE(st.first_name,''),CASE WHEN COALESCE(st.last_name,'')='' THEN '' ELSE CONCAT(' ',st.last_name) END))";
    $s = $pdo->prepare(
        "SELECT
            r.*,
            {$nameSql} AS student_name,
            st.admission_no,
            GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') AS payment_methods,
            GROUP_CONCAT(DISTINCT NULLIF(p.reference_no,'') ORDER BY p.id SEPARATOR ', ') AS reference_numbers
         FROM fee_receipts r
         INNER JOIN students st
            ON st.id = r.student_id
           AND st.tenant_id = r.tenant_id
         LEFT JOIN fee_payments p
            ON p.receipt_id = r.id
           AND p.tenant_id = r.tenant_id
           AND p.status <> 'failed'
         LEFT JOIN payment_methods pm
            ON pm.id = p.payment_method_id
           AND pm.tenant_id = p.tenant_id
         WHERE r.id = :id
           AND r.tenant_id = :tenant_id
         GROUP BY r.id
         LIMIT 1"
    );
    $s->execute(['id' => $id, 'tenant_id' => $scope['tenant_id']]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new InvalidArgumentException('Receipt not found.');
    return $row;
}

function frSchoolProfile(PDO $pdo, array $scope, int $branchId): array
{
    $profile = [];
    if (frTable($pdo, 'school_profile')) {
        $s = $pdo->prepare(
            "SELECT * FROM school_profile
             WHERE tenant_id = :tenant_id
               AND (branch_id = :branch_id OR branch_id IS NULL)
             ORDER BY (branch_id = :branch_order) DESC, id DESC
             LIMIT 1"
        );
        $s->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $branchId,
            'branch_order' => $branchId,
        ]);
        $profile = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $tenant = [];
    if (frTable($pdo, 'tenants')) {
        $s = $pdo->prepare("SELECT school_name,email,mobile,address,logo_path FROM tenants WHERE id=:tenant_id LIMIT 1");
        $s->execute(['tenant_id' => $scope['tenant_id']]);
        $tenant = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $branch = [];
    if ($branchId > 0 && frTable($pdo, 'branches')) {
        $s = $pdo->prepare("SELECT branch_code,branch_name,address,phone FROM branches WHERE id=:branch_id AND tenant_id=:tenant_id LIMIT 1");
        $s->execute(['branch_id' => $branchId, 'tenant_id' => $scope['tenant_id']]);
        $branch = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $schoolName = trim((string)($profile['school_name'] ?? $tenant['school_name'] ?? 'School'));
    $logoPath = trim((string)($profile['logo_path'] ?? $tenant['logo_path'] ?? ''));
    $phone = trim((string)($profile['phone_number'] ?? $tenant['mobile'] ?? $branch['phone'] ?? ''));
    $email = trim((string)($profile['email_address'] ?? $tenant['email'] ?? ''));

    $address1 = trim(implode(', ', array_values(array_filter([
        trim((string)($profile['address_line1'] ?? '')),
        trim((string)($profile['address_line2'] ?? '')),
    ], static fn($v) => $v !== ''))));

    $cityState = [];
    foreach (['city', 'district', 'state_name'] as $key) {
        $v = trim((string)($profile[$key] ?? ''));
        if ($v !== '' && !in_array(mb_strtolower($v), array_map('mb_strtolower', $cityState), true)) $cityState[] = $v;
    }
    $address2 = implode(', ', $cityState);
    $postal = trim((string)($profile['postal_code'] ?? ''));
    if ($postal !== '') $address2 .= ($address2 !== '' ? ' - ' : '') . $postal;

    if ($address1 === '' && $address2 === '') $address1 = trim((string)($branch['address'] ?? $tenant['address'] ?? ''));

    return [
        'school_name' => $schoolName !== '' ? $schoolName : 'School',
        'logo_path' => $logoPath,
        'phone' => $phone,
        'email' => $email,
        'address1' => $address1,
        'address2' => $address2,
        'branch_name' => trim((string)($branch['branch_name'] ?? '')),
        'branch_code' => trim((string)($branch['branch_code'] ?? '')),
    ];
}

function frAssetUrl(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') return '';
    if (preg_match('~^(?:https?:)?//~i', $path) || str_starts_with($path, 'data:')) return $path;
    $path = ltrim($path, '/');
    return function_exists('app_url') ? app_url($path) : '../' . $path;
}

function frStudentContext(PDO $pdo, array $scope, array $receipt): array
{
    $fatherSql = frTable($pdo, 'student_guardians') && frTable($pdo, 'guardians')
        ? "COALESCE(
                (SELECT g1.guardian_name
                 FROM student_guardians sg1
                 INNER JOIN guardians g1 ON g1.id=sg1.guardian_id AND g1.tenant_id=st.tenant_id
                 WHERE sg1.student_id=st.id AND LOWER(COALESCE(g1.relationship,''))='father'
                 ORDER BY sg1.is_primary DESC,g1.id LIMIT 1),
                (SELECT g2.guardian_name
                 FROM student_guardians sg2
                 INNER JOIN guardians g2 ON g2.id=sg2.guardian_id AND g2.tenant_id=st.tenant_id
                 WHERE sg2.student_id=st.id
                 ORDER BY sg2.is_primary DESC,g2.id LIMIT 1),
                ''
           )"
        : "''";

    $userJoin = frTable($pdo, 'users')
        ? "LEFT JOIN users u ON u.id=:collected_by AND u.tenant_id=st.tenant_id"
        : '';
    $collectorSql = frTable($pdo, 'users') ? "COALESCE(u.name,'')" : "''";

    $s = $pdo->prepare(
        "SELECT
            st.mobile,
            COALESCE(e.roll_no,'') AS roll_no,
            ay.year_name AS academic_year_name,
            COALESCE(c.class_name,'') AS class_name,
            COALESCE(sec.section_name,'') AS section_name,
            COALESCE(b.branch_name,'') AS branch_name,
            COALESCE(b.branch_code,'') AS branch_code,
            {$collectorSql} AS collected_by_name,
            {$fatherSql} AS father_name
         FROM students st
         INNER JOIN academic_years ay
            ON ay.id=:academic_year_id
           AND ay.tenant_id=st.tenant_id
         LEFT JOIN student_enrollments e
            ON e.student_id=st.id
           AND e.tenant_id=st.tenant_id
           AND e.academic_year_id=:academic_year_id_enrollment
         LEFT JOIN classes c
            ON c.id=e.class_id
           AND c.tenant_id=e.tenant_id
         LEFT JOIN sections sec
            ON sec.id=e.section_id
           AND sec.tenant_id=e.tenant_id
         LEFT JOIN branches b
            ON b.id=:branch_id
           AND b.tenant_id=st.tenant_id
         {$userJoin}
         WHERE st.id=:student_id
           AND st.tenant_id=:tenant_id
         ORDER BY (e.enrollment_status='active') DESC,e.id DESC
         LIMIT 1"
    );

    $params = [
        'academic_year_id' => (int)$receipt['academic_year_id'],
        'academic_year_id_enrollment' => (int)$receipt['academic_year_id'],
        'branch_id' => (int)$receipt['branch_id'],
        'student_id' => (int)$receipt['student_id'],
        'tenant_id' => $scope['tenant_id'],
    ];
    if (frTable($pdo, 'users')) $params['collected_by'] = (int)$receipt['collected_by'];
    $s->execute($params);
    return $s->fetch(PDO::FETCH_ASSOC) ?: [];
}

function frIntegerWords(int $number): string
{
    if ($number === 0) return 'Zero';
    $ones = ['', 'One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['', '', 'Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $under100 = static function(int $n) use ($ones, $tens): string {
        if ($n < 20) return $ones[$n];
        return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    };
    $under1000 = static function(int $n) use ($under100): string {
        if ($n < 100) return $under100($n);
        return trim($under100(intdiv($n, 100)) . ' Hundred ' . ($n % 100 ? $under100($n % 100) : ''));
    };

    $parts = [];
    $crore = intdiv($number, 10000000); $number %= 10000000;
    $lakh = intdiv($number, 100000); $number %= 100000;
    $thousand = intdiv($number, 1000); $number %= 1000;
    if ($crore) $parts[] = frIntegerWords($crore) . ' Crore';
    if ($lakh) $parts[] = $under1000($lakh) . ' Lakh';
    if ($thousand) $parts[] = $under1000($thousand) . ' Thousand';
    if ($number) $parts[] = $under1000($number);
    return trim(implode(' ', $parts));
}

function frAmountWords(float $amount): string
{
    $amount = round(max(0, $amount), 2);
    $rupees = (int)floor($amount + 0.00001);
    $paise = (int)round(($amount - $rupees) * 100);
    if ($paise >= 100) { $rupees++; $paise -= 100; }
    $text = frIntegerWords($rupees) . ' Rupee' . ($rupees === 1 ? '' : 's');
    if ($paise > 0) $text .= ' and ' . frIntegerWords($paise) . ' Paise';
    return $text . ' Only';
}

function frReceiptLines(PDO $pdo, array $scope, array $receipt): array
{
    $s = $pdo->prepare(
        "SELECT
            ri.*,
            COALESCE(sfi.original_amount,0) AS original_amount
         FROM fee_receipt_items ri
         LEFT JOIN student_fee_items sfi
            ON sfi.id=ri.student_fee_item_id
           AND sfi.tenant_id=ri.tenant_id
         WHERE ri.tenant_id=:tenant_id
           AND ri.receipt_id=:receipt_id
         ORDER BY ri.id"
    );
    $s->execute([
        'tenant_id' => $scope['tenant_id'],
        'receipt_id' => (int)$receipt['id'],
    ]);
    $all = $s->fetchAll(PDO::FETCH_ASSOC);
    if (!$all) return [];

    $itemIds = [];
    foreach ($all as $row) {
        $itemId = (int)($row['student_fee_item_id'] ?? 0);
        if ($itemId > 0) $itemIds[$itemId] = true;
    }

    $prior = [];
    if ($itemIds) {
        $placeholders = [];
        $params = [
            'tenant_id' => $scope['tenant_id'],
            // Use separate named placeholders for native MySQL PDO prepares.
            // Reusing :receipt_date twice causes SQLSTATE[HY093] when
            // ATTR_EMULATE_PREPARES is disabled.
            'receipt_date_lt' => (string)$receipt['receipt_date'],
            'receipt_date_eq' => (string)$receipt['receipt_date'],
            'receipt_id' => (int)$receipt['id'],
        ];
        $i = 0;
        foreach (array_keys($itemIds) as $itemId) {
            $key = 'item_' . $i++;
            $placeholders[] = ':' . $key;
            $params[$key] = $itemId;
        }

        $q = $pdo->prepare(
            "SELECT
                pri.student_fee_item_id,
                COALESCE(SUM(pri.paid_amount),0) AS prior_paid,
                COALESCE(SUM(pri.discount_amount),0) AS prior_discount
             FROM fee_receipt_items pri
             INNER JOIN fee_receipts pr
                ON pr.id=pri.receipt_id
               AND pr.tenant_id=pri.tenant_id
             WHERE pri.tenant_id=:tenant_id
               AND pri.student_fee_item_id IN (" . implode(',', $placeholders) . ")
               AND pr.payment_status <> 'reversed'
               AND (
                    pr.receipt_date < :receipt_date_lt
                    OR (pr.receipt_date = :receipt_date_eq AND pr.id < :receipt_id)
               )
             GROUP BY pri.student_fee_item_id"
        );
        $q->execute($params);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $prior[(int)$row['student_fee_item_id']] = [
                'paid' => (float)$row['prior_paid'],
                'discount' => (float)$row['prior_discount'],
            ];
        }
    }

    $display = array_values(array_filter($all, static function(array $row): bool {
        return (float)($row['paid_amount'] ?? 0) > 0.009 || (float)($row['discount_amount'] ?? 0) > 0.009;
    }));
    if (!$display) $display = $all;

    $result = [];
    foreach ($display as $row) {
        $itemId = (int)($row['student_fee_item_id'] ?? 0);
        $original = max(0, (float)($row['original_amount'] ?? 0));
        if ($original <= 0) $original = max(0, (float)($row['gross_amount'] ?? 0));

        $priorPaid = (float)($prior[$itemId]['paid'] ?? 0);
        $priorDiscount = (float)($prior[$itemId]['discount'] ?? 0);
        $currentDiscount = max(0, (float)($row['discount_amount'] ?? 0));
        $currentPaid = max(0, (float)($row['paid_amount'] ?? $row['amount'] ?? 0));

        /* Net due shown on receipt is AFTER the current receipt discount. */
        $dueBeforeCurrentPayment = max(0, round($original - $priorPaid - $priorDiscount - $currentDiscount, 2));
        $balanceAfterPayment = max(0, round($dueBeforeCurrentPayment - $currentPaid, 2));

        $row['print_due_amount'] = $dueBeforeCurrentPayment;
        $row['print_paid_amount'] = $currentPaid;
        $row['print_balance_amount'] = $balanceAfterPayment;
        $result[] = $row;
    }

    return $result;
}

if (!isset($pdo) || !$pdo instanceof PDO) frFail('Database connection unavailable.', 500);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$scope = frScope();
if ($scope['tenant_id'] <= 0) frFail('School tenant session was not found.', 401);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) frFail('Receipt ID is required.');

try {
    $receipt = frReceipt($pdo, $scope, $id);
    $profile = frSchoolProfile($pdo, $scope, (int)$receipt['branch_id']);
    $student = frStudentContext($pdo, $scope, $receipt);
    $lines = frReceiptLines($pdo, $scope, $receipt);
} catch (Throwable $e) {
    frFail($e->getMessage(), 422);
}

$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$logoUrl = frAssetUrl((string)$profile['logo_path']);
$schoolName = mb_strtoupper(trim((string)$profile['school_name']));
$receiptDate = (string)($receipt['receipt_date'] ?? '');
$receiptDateDisplay = $receiptDate !== '' ? date('d-m-Y', strtotime($receiptDate)) : '-';
$paymentMode = trim((string)($receipt['payment_methods'] ?? '')) ?: '-';
$referenceNo = trim((string)($receipt['reference_numbers'] ?? '')) ?: '-';
$collector = trim((string)($student['collected_by_name'] ?? '')) ?: '-';
$academicYear = trim((string)($student['academic_year_name'] ?? '')) ?: '-';
$className = trim((string)($student['class_name'] ?? ''));
$sectionName = trim((string)($student['section_name'] ?? ''));
$classSection = trim($className . ($sectionName !== '' ? ' - ' . $sectionName : '')) ?: '-';
$rollNo = trim((string)($student['roll_no'] ?? '')) ?: '-';
$branchName = trim((string)($student['branch_name'] ?? $profile['branch_name'] ?? ''));
$branchCode = trim((string)($student['branch_code'] ?? $profile['branch_code'] ?? ''));
$branchDisplay = $branchName;
if ($branchCode !== '') $branchDisplay .= ($branchDisplay !== '' ? ' ' : '') . '(' . $branchCode . ')';
if ($branchDisplay === '') $branchDisplay = '-';

$totalDue = 0.0;
$totalPaid = 0.0;
$totalBalance = 0.0;
foreach ($lines as $line) {
    $totalDue += (float)$line['print_due_amount'];
    $totalPaid += (float)$line['print_paid_amount'];
    $totalBalance += (float)$line['print_balance_amount'];
}
$totalDue = round($totalDue, 2);
$totalPaid = round($totalPaid, 2);
$totalBalance = round($totalBalance, 2);

/* Receipt paid_amount is the canonical payment total. Allocation should match it. */
$receiptPaid = round(max(0, (float)$receipt['paid_amount']), 2);
if (abs($totalPaid - $receiptPaid) > 0.01) {
    $totalPaid = $receiptPaid;
    $totalBalance = max(0, round($totalDue - $totalPaid, 2));
}

$autoPrint = ((string)($_GET['autoprint'] ?? '0')) === '1';
$returnUrl = trim((string)($_GET['return'] ?? ''));
if ($returnUrl === '') $returnUrl = function_exists('app_url') ? app_url('school/fee-collection.php') : 'fee-collection.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($receipt['receipt_no']) ?> - Fee Receipt</title>
<style id="receiptRasterCss">
*{box-sizing:border-box}
.receipt-card{width:794px;height:1123px;background:#fff;color:#050b1a;border:2px solid #173f82;font-family:Arial,Helvetica,sans-serif;overflow:hidden;position:relative}
.receipt-header{height:195px;display:grid;grid-template-columns:170px 1fr 280px;border-bottom:1.5px solid #173f82}
.logo-zone{display:flex;align-items:center;justify-content:center;padding:18px 10px}.logo-zone img{max-width:125px;max-height:125px;object-fit:contain;display:block}
.logo-fallback{width:108px;height:108px;border:2px solid #173f82;border-radius:50%;display:flex;align-items:center;justify-content:center;text-align:center;color:#173f82;font-weight:900;font-size:14px}
.school-zone{display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:15px 8px}.school-name{font-size:25px;line-height:1.05;font-weight:900;color:#0d397c;letter-spacing:.2px;margin-bottom:15px;max-width:360px}.school-address,.school-contact{font-size:11px;line-height:1.6;color:#0d1320}
.receipt-meta{padding:16px 20px 12px 12px;display:flex;flex-direction:column;justify-content:flex-start}.receipt-chip{background:#193f81;color:#fff;font-size:15px;font-weight:900;text-align:center;padding:12px 10px;border-radius:3px;margin-bottom:16px}.meta-row,.info-row{display:grid;grid-template-columns:108px 14px 1fr;align-items:start;font-size:12px;line-height:1.42;margin:5px 0}.meta-row .label,.info-row .label{font-weight:800}.meta-row .colon,.info-row .colon{font-weight:900}.meta-row .value,.info-row .value{font-weight:700;overflow-wrap:anywhere}
.details{height:202px;display:grid;grid-template-columns:1.15fr .85fr;border-bottom:1.5px solid #173f82}.details-box{padding:19px 20px}.details-box+.details-box{border-left:1.5px solid #173f82}.details-box .info-row{grid-template-columns:145px 14px 1fr;font-size:12px;margin:7px 0}.details-box.right .info-row{grid-template-columns:135px 14px 1fr}
.fee-area{padding:10px 12px 0}.fee-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px}.fee-table th{background:#193f81;color:#fff;border:1px solid #173f82;padding:13px 10px;font-weight:900;text-align:center}.fee-table td{border:1px solid #9bb0d2;padding:12px 10px;vertical-align:middle}.fee-table td.center{text-align:center}.fee-table td.right{text-align:right;font-variant-numeric:tabular-nums}.fee-table td.bold{font-weight:800}.fee-table .sum-row td{font-weight:900}.fee-table .sum-label{text-align:right}
.amount-line{display:grid;grid-template-columns:1fr 175px 160px;border:1px solid #9bb0d2;border-top:0;min-height:50px}.amount-words{padding:14px 10px;font-size:12px;display:flex;align-items:center;gap:8px}.amount-words strong{font-weight:900}.total-label,.total-value{display:flex;align-items:center;justify-content:center;background:#193f81;color:#fff;font-weight:900;font-size:15px;border-left:1px solid #fff}.total-value{font-size:19px}.balance-row{padding:16px 10px 22px;font-size:13px;font-weight:900;border-bottom:1.5px solid #173f82}
.footer{height:325px;position:relative;padding:52px 22px 18px}.computer-note{font-size:10px;margin-bottom:18px}.thanks{font-size:12px;font-style:italic;font-weight:900;color:#0d397c}.signature-block{position:absolute;right:28px;bottom:52px;width:245px;text-align:center;font-size:11px}.signature-line{height:1px;background:#1d2939;width:100%;margin-bottom:12px}.signature-title{font-size:13px;font-weight:900}.signature-sub{margin-top:8px;color:#34425a;font-size:10px}
</style>
<style>
@page{size:A4 portrait;margin:0}html,body{margin:0;padding:0;background:#edf2f8;font-family:Arial,Helvetica,sans-serif}body{padding:16px}.stage{width:794px;max-width:100%;margin:0 auto}#receiptSource{width:794px;max-width:100%}#receiptImageWrap{display:none;width:794px;max-width:100%;margin:0 auto;background:#fff}#receiptImage{display:block;width:794px;max-width:100%;height:auto;background:#fff}body.image-ready #receiptSource{display:none}body.image-ready #receiptImageWrap{display:block}.action-bar{width:794px;max-width:100%;margin:12px auto 0;display:flex;justify-content:center;gap:10px}.action-bar button,.action-bar a{border:0;border-radius:8px;padding:10px 18px;background:#193f81;color:#fff;text-decoration:none;font-weight:800;cursor:pointer;font-size:13px}.action-bar a{background:#64748b}@media print{html,body{width:210mm;height:297mm;background:#fff}body{padding:0}.action-bar{display:none!important}.stage,#receiptImageWrap,#receiptImage{width:210mm!important;max-width:none!important;margin:0!important}body.image-ready #receiptImageWrap{display:block!important}body.image-ready #receiptSource{display:none!important}#receiptImage{height:297mm!important;object-fit:fill!important;page-break-after:avoid}}
</style>
</head>
<body>
<div class="stage">
<div id="receiptSource">
<div class="receipt-card" id="receiptCard">
<header class="receipt-header">
  <div class="logo-zone"><?php if($logoUrl!==''): ?><img src="<?= $e($logoUrl) ?>" alt="School Logo" crossorigin="anonymous"><?php else: ?><div class="logo-fallback">SCHOOL<br>LOGO</div><?php endif; ?></div>
  <div class="school-zone">
    <div class="school-name"><?= $e($schoolName) ?></div>
    <?php if($profile['address1']!==''): ?><div class="school-address"><?= $e($profile['address1']) ?></div><?php endif; ?>
    <?php if($profile['address2']!==''): ?><div class="school-address"><?= $e($profile['address2']) ?></div><?php endif; ?>
    <div class="school-contact"><?php if($profile['phone']!==''): ?>Phone : <?= $e($profile['phone']) ?><?php endif; ?><?php if($profile['phone']!==''&&$profile['email']!==''): ?> &nbsp;|&nbsp; <?php endif; ?><?php if($profile['email']!==''): ?>Email : <?= $e($profile['email']) ?><?php endif; ?></div>
  </div>
  <div class="receipt-meta">
    <div class="receipt-chip">FEE RECEIPT</div>
    <div class="meta-row"><span class="label">Receipt No.</span><span class="colon">:</span><span class="value"><?= $e($receipt['receipt_no']) ?></span></div>
    <div class="meta-row"><span class="label">Date</span><span class="colon">:</span><span class="value"><?= $e($receiptDateDisplay) ?></span></div>
    <div class="meta-row"><span class="label">Academic Year</span><span class="colon">:</span><span class="value"><?= $e($academicYear) ?></span></div>
  </div>
</header>
<section class="details">
  <div class="details-box">
    <div class="info-row"><span class="label">Student Name</span><span class="colon">:</span><span class="value"><?= $e($receipt['student_name']?:'-') ?></span></div>
    <div class="info-row"><span class="label">Admission No.</span><span class="colon">:</span><span class="value"><?= $e($receipt['admission_no']?:'-') ?></span></div>
    <div class="info-row"><span class="label">Class &amp; Section</span><span class="colon">:</span><span class="value"><?= $e($classSection) ?></span></div>
    <div class="info-row"><span class="label">Roll No.</span><span class="colon">:</span><span class="value"><?= $e($rollNo) ?></span></div>
    <div class="info-row"><span class="label">Father's Name</span><span class="colon">:</span><span class="value"><?= $e($student['father_name']?:'-') ?></span></div>
  </div>
  <div class="details-box right">
    <div class="info-row"><span class="label">Payment Mode</span><span class="colon">:</span><span class="value"><?= $e($paymentMode) ?></span></div>
    <div class="info-row"><span class="label">Transaction No.</span><span class="colon">:</span><span class="value"><?= $e($referenceNo) ?></span></div>
    <div class="info-row"><span class="label">Payment Date</span><span class="colon">:</span><span class="value"><?= $e($receiptDateDisplay) ?></span></div>
    <div class="info-row"><span class="label">Collected By</span><span class="colon">:</span><span class="value"><?= $e($collector) ?></span></div>
    <div class="info-row"><span class="label">Branch</span><span class="colon">:</span><span class="value"><?= $e($branchDisplay) ?></span></div>
  </div>
</section>
<section class="fee-area">
<table class="fee-table">
<colgroup><col style="width:8%"><col style="width:50%"><col style="width:22%"><col style="width:20%"></colgroup>
<thead><tr><th>S.No.</th><th>Particulars</th><th>Due Amount (&#8377;)</th><th>Paid Amount (&#8377;)</th></tr></thead>
<tbody>
<?php if($lines): foreach($lines as $i=>$line):
    $period=trim((string)($line['period_label']??''));
    $particular=trim((string)($line['item_name']??'Fee'));
    if($period!==''&&!str_contains(mb_strtolower($particular),mb_strtolower($period)))$particular.=' ('.$period.')';
?>
<tr><td class="center"><?= $i+1 ?></td><td class="bold"><?= $e($particular?:'Fee') ?></td><td class="right"><?= number_format((float)$line['print_due_amount'],2) ?></td><td class="right"><?= number_format((float)$line['print_paid_amount'],2) ?></td></tr>
<?php endforeach; else: ?>
<tr><td class="center">1</td><td class="bold">Fee Payment</td><td class="right"><?= number_format($totalDue,2) ?></td><td class="right"><?= number_format($totalPaid,2) ?></td></tr>
<?php endif; ?>
<tr class="sum-row"><td colspan="2" class="sum-label">Total</td><td class="right"><?= number_format($totalDue,2) ?></td><td class="right"><?= number_format($totalPaid,2) ?></td></tr>
</tbody>
</table>
<div class="amount-line"><div class="amount-words"><strong>Amount in Words :</strong><span><?= $e(frAmountWords($totalPaid)) ?></span></div><div class="total-label">Total Paid (&#8377;)</div><div class="total-value"><?= number_format($totalPaid,2) ?></div></div>
<div class="balance-row">Balance Amount (&#8377;) : <?= number_format($totalBalance,2) ?></div>
</section>
<footer class="footer"><div class="computer-note">* This is a computer generated receipt.</div><div class="thanks">Thank you for your payment!</div><div class="signature-block"><div class="signature-line"></div><div class="signature-title">Authorized Signatory</div><div class="signature-sub">Collected By : <?= $e($collector) ?></div></div></footer>
</div>
</div>
<div id="receiptImageWrap"><img id="receiptImage" alt="Fee Receipt"></div>
</div>
<div class="action-bar"><button id="printButton" type="button">Print Receipt</button><a href="<?= $e($returnUrl) ?>">Back to Fee Collection</a></div>
<script>
(function(){
  'use strict';
  const card=document.getElementById('receiptCard');
  const image=document.getElementById('receiptImage');
  const imageWrap=document.getElementById('receiptImageWrap');
  const css=document.getElementById('receiptRasterCss').textContent||'';
  let renderPromise=null;
  function fileToDataUrl(blob){return new Promise(function(resolve){const r=new FileReader();r.onload=function(){resolve(String(r.result||''));};r.onerror=function(){resolve('');};r.readAsDataURL(blob);});}
  async function inlineImages(root){const nodes=Array.from(root.querySelectorAll('img'));await Promise.all(nodes.map(async function(node){const src=node.getAttribute('src')||'';if(src===''||src.startsWith('data:'))return;try{const response=await fetch(src,{credentials:'same-origin',cache:'no-store'});if(!response.ok)return;const data=await fileToDataUrl(await response.blob());if(data)node.setAttribute('src',data);}catch(e){}}));}
  async function renderReceiptImage(){if(renderPromise)return renderPromise;renderPromise=(async function(){const clone=card.cloneNode(true);clone.removeAttribute('id');await inlineImages(clone);const holder=document.createElement('div');holder.setAttribute('xmlns','http://www.w3.org/1999/xhtml');holder.setAttribute('style','margin:0;padding:0;width:794px;height:1123px;background:#fff;');const style=document.createElement('style');style.textContent=css;holder.appendChild(style);holder.appendChild(clone);const serialized=new XMLSerializer().serializeToString(holder);const svg='<svg xmlns="http://www.w3.org/2000/svg" width="794" height="1123" viewBox="0 0 794 1123"><foreignObject width="794" height="1123">'+serialized+'</foreignObject></svg>';const blob=new Blob([svg],{type:'image/svg+xml;charset=utf-8'});const url=URL.createObjectURL(blob);try{const svgImage=await new Promise(function(resolve,reject){const im=new Image();im.onload=function(){resolve(im);};im.onerror=function(){reject(new Error('Receipt image rendering failed.'));};im.src=url;});const scale=2;const canvas=document.createElement('canvas');canvas.width=794*scale;canvas.height=1123*scale;const context=canvas.getContext('2d');context.fillStyle='#fff';context.fillRect(0,0,canvas.width,canvas.height);context.drawImage(svgImage,0,0,canvas.width,canvas.height);image.src=canvas.toDataURL('image/png',1.0);await new Promise(function(resolve){if(image.complete)resolve();else image.onload=resolve;});document.body.classList.add('image-ready');imageWrap.style.display='block';return true;}finally{URL.revokeObjectURL(url);}})().catch(function(error){console.warn(error);document.body.classList.remove('image-ready');imageWrap.style.display='none';return false;});return renderPromise;}
  async function printReceipt(){await renderReceiptImage();window.focus();window.print();}
  window.renderReceiptImage=renderReceiptImage;
  document.getElementById('printButton').addEventListener('click',printReceipt);
  window.addEventListener('load',function(){setTimeout(renderReceiptImage,80);},{once:true});
})();
</script>
<?php if($autoPrint): ?>
<script>
(function(){
  const returnUrl=<?= json_encode($returnUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  let done=false;
  function back(){if(done)return;done=true;try{if(window.opener&&!window.opener.closed){window.opener.focus();window.close();return;}}catch(e){}window.location.replace(returnUrl);}
  window.addEventListener('afterprint',function(){setTimeout(back,100);},{once:true});
  window.addEventListener('load',function(){Promise.resolve(window.renderReceiptImage?window.renderReceiptImage():false).finally(function(){setTimeout(function(){window.focus();window.print();},300);});},{once:true});
})();
</script>
<?php endif; ?>
</body>
</html>

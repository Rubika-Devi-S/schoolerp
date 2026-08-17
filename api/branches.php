<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

/*
 * This is a SUPER ADMIN platform API stored in the shared /api directory.
 * The common bootstrap normally treats every /api/*.php request made by a
 * platform user as a school-operational API and therefore requires an active
 * Support Access session. Branch Management here is platform-level and must
 * not require Support Access. Temporarily present this bootstrap load as a
 * neutral login route, then restore the real script path immediately.
 *
 * IMPORTANT: This does not weaken school API protection. This endpoint still
 * performs its own strict Super Admin authentication in brRequireSuperAdmin().
 */
$brOriginalScriptName = array_key_exists('SCRIPT_NAME', $_SERVER)
    ? (string)$_SERVER['SCRIPT_NAME']
    : null;

$_SERVER['SCRIPT_NAME'] = '/login.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($brOriginalScriptName !== null) {
    $_SERVER['SCRIPT_NAME'] = $brOriginalScriptName;
} else {
    unset($_SERVER['SCRIPT_NAME']);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SUPER_ADMIN_BRANCH_BUILD = '2026-08-14-strict-branch-isolation-v48';

function brOut(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function brTable(PDO $pdo, string $table): bool
{
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
    $s->execute([$table]);
    return (int)$s->fetchColumn() > 0;
}

function brColumn(PDO $pdo, string $table, string $column): bool
{
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
    $s->execute([$table, $column]);
    return (int)$s->fetchColumn() > 0;
}

function brUser(): array
{
    $u = function_exists('current_user') ? current_user() : [];
    return is_array($u) ? $u : [];
}

function brRequireSuperAdmin(PDO $pdo): array
{
    $u = brUser();
    $userId = (int)($u['id'] ?? $u['user_id'] ?? $_SESSION['user_id'] ?? 0);
    $roleId = (int)($u['role_id'] ?? $_SESSION['role_id'] ?? 0);
    $roleKey = strtolower(trim((string)($u['role_key'] ?? $_SESSION['role_key'] ?? '')));

    if ($roleKey === '' && $roleId > 0 && brTable($pdo, 'roles')) {
        try {
            $s = $pdo->prepare("SELECT role_key FROM roles WHERE id=? LIMIT 1");
            $s->execute([$roleId]);
            $roleKey = strtolower(trim((string)($s->fetchColumn() ?: '')));
        } catch (Throwable $e) {
            error_log('branches api role lookup: ' . $e->getMessage());
        }
    }

    if ($userId <= 0) brOut(false, 'Login session is required.', ['build' => SUPER_ADMIN_BRANCH_BUILD], 401);
    $allowed = $roleId === 1 || in_array($roleKey, ['super_admin','super-admin','superadministrator','super_administrator','super-administrator'], true);
    if (!$allowed) brOut(false, 'Access denied.', ['build' => SUPER_ADMIN_BRANCH_BUILD], 403);

    return ['user_id'=>$userId,'role_id'=>$roleId,'role_key'=>$roleKey];
}


function brOperationalBranchTables(): array
{
    /*
     * Only branch-owned operational tables are included.
     * School-wide configuration tables are intentionally excluded.
     */
    return [
        'students',
        'student_enrollments',
        'student_attendance',
        'attendance_change_requests',
        'staff_members',
        'employees',
        'classes',
        'class_management_classes',
        'school_sections',
        'sections',
        'school_subjects',
        'class_subject_allocations',
        'class_teacher_allocations',
        'class_timetable_entries',
        'subject_class_assignments',
        'section_subject_assignments',
        'section_timetable_assignments',
        'subject_teacher_timetable_assignments',
        'fee_structures',
        'student_fee_assignments',
        'student_fee_items',
        'fee_receipts',
        'fee_receipt_items',
        'fee_payments',
        'exams',
        'exam_schedules',
        'exam_marks',
        'exam_results',
        'student_transport_assignments',
        'academic_year_module_records',
        'extra_fee_batches',
        'extra_fee_student_assignments',
        'employee_salary_structures',
        'student_certificate_logs',
        'student_management_profiles',
        'student_profile_extras'
    ];
}

function brPinLegacyRowsToMainBranch(
    PDO $pdo,
    int $tenantId
): int {
    if ($tenantId <= 0) {
        throw new InvalidArgumentException(
            'Invalid School for branch isolation.'
        );
    }

    $mainStmt = $pdo->prepare(
        "SELECT id
         FROM branches
         WHERE tenant_id = :tenant_id
           AND status = 'active'
         ORDER BY is_main DESC, id ASC
         LIMIT 1
         FOR UPDATE"
    );

    $mainStmt->execute([
        'tenant_id' => $tenantId,
    ]);

    $mainBranchId = (int)$mainStmt->fetchColumn();

    /*
     * If this is the very first branch, there is no historical branch to pin.
     */
    if ($mainBranchId <= 0) {
        return 0;
    }

    $updated = 0;

    foreach (brOperationalBranchTables() as $table) {
        if (
            !brTable($pdo, $table)
            || !brColumn($pdo, $table, 'tenant_id')
            || !brColumn($pdo, $table, 'branch_id')
        ) {
            continue;
        }

        /*
         * Table names come only from the fixed internal whitelist above.
         */
        $sql =
            "UPDATE `{$table}`
             SET branch_id = :branch_id
             WHERE tenant_id = :tenant_id
               AND (branch_id IS NULL OR branch_id = 0)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'branch_id' => $mainBranchId,
            'tenant_id' => $tenantId,
        ]);

        $updated += $stmt->rowCount();
    }

    return $updated;
}

function brCsrf(array $input): void
{
    $session = (string)($_SESSION['branch_management_csrf'] ?? '');
    $request = (string)($input['csrf_token'] ?? '');
    if ($session === '' || $request === '' || !hash_equals($session, $request)) {
        brOut(false, 'Invalid or expired CSRF token. Refresh the page.', ['build'=>SUPER_ADMIN_BRANCH_BUILD], 419);
    }
}

function brSchools(PDO $pdo): array
{
    if (!brTable($pdo, 'tenants')) return [];
    $sql = "SELECT id, tenant_code, school_name, status FROM tenants ORDER BY school_name, id";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function brClean(mixed $value, int $max, string $label, bool $required = true): string
{
    $value = trim((string)$value);
    if ($required && $value === '') throw new InvalidArgumentException($label . ' is required.');
    if (mb_strlen($value) > $max) throw new InvalidArgumentException($label . ' cannot exceed ' . $max . ' characters.');
    return $value;
}

function brPhone(mixed $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $value)) throw new InvalidArgumentException('Phone number is invalid.');
    return $value;
}

function brValidate(PDO $pdo, array $input): array
{
    $id = max(0, (int)($input['id'] ?? 0));
    $tenantId = max(0, (int)($input['tenant_id'] ?? 0));
    if ($tenantId <= 0) throw new InvalidArgumentException('School is required.');

    $tenantStmt = $pdo->prepare("SELECT id FROM tenants WHERE id=? LIMIT 1");
    $tenantStmt->execute([$tenantId]);
    if (!(int)$tenantStmt->fetchColumn()) throw new InvalidArgumentException('Selected school was not found.');

    $name = brClean($input['branch_name'] ?? '', 150, 'Branch Name');
    $code = strtoupper(brClean($input['branch_code'] ?? '', 30, 'Branch Code', false));
    if ($code !== '' && !preg_match('/^[A-Z0-9][A-Z0-9_-]{0,29}$/', $code)) {
        throw new InvalidArgumentException('Branch Code can contain only letters, numbers, hyphen and underscore.');
    }

    $status = strtolower(trim((string)($input['status'] ?? 'active')));
    if (!in_array($status, ['active','inactive'], true)) throw new InvalidArgumentException('Status is invalid.');
    $isMain = (int)($input['is_main'] ?? 0) === 1 ? 1 : 0;
    if ($isMain === 1) $status = 'active';

    return [
        'id'=>$id,
        'tenant_id'=>$tenantId,
        'branch_name'=>$name,
        'branch_code'=>$code,
        'phone'=>brPhone($input['phone'] ?? ''),
        'address'=>brClean($input['address'] ?? '', 1000, 'Address', false),
        'is_main'=>$isMain,
        'status'=>$status,
    ];
}

function brGenerateCode(PDO $pdo, int $tenantId): string
{
    for ($i=1; $i<=999; $i++) {
        $code = 'BR-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $s = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE tenant_id=? AND branch_code=?");
        $s->execute([$tenantId, $code]);
        if ((int)$s->fetchColumn() === 0) return $code;
    }
    return 'BR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function brUsage(PDO $pdo, int $branchId): array
{
    $users = 0; $students = 0;
    if (brTable($pdo, 'users') && brColumn($pdo, 'users', 'default_branch_id')) {
        $s=$pdo->prepare("SELECT COUNT(*) FROM users WHERE default_branch_id=?"); $s->execute([$branchId]); $users=(int)$s->fetchColumn();
    }
    if (brTable($pdo, 'students') && brColumn($pdo, 'students', 'branch_id')) {
        $s=$pdo->prepare("SELECT COUNT(*) FROM students WHERE branch_id=?"); $s->execute([$branchId]); $students=(int)$s->fetchColumn();
    }
    return ['users'=>$users,'students'=>$students];
}

function brAudit(PDO $pdo, array $user, string $action, int $branchId, array $values = []): void
{
    if (!brTable($pdo, 'activity_logs')) return;
    try {
        $cols = ['tenant_id','branch_id','user_id','role_id','module_name','action_key','table_name','record_id','new_values','description','ip_address','user_agent'];
        foreach ($cols as $c) if (!brColumn($pdo,'activity_logs',$c)) return;
        $tenantId = (int)($values['tenant_id'] ?? 0);
        $s=$pdo->prepare("INSERT INTO activity_logs (tenant_id,branch_id,user_id,role_id,module_name,action_key,table_name,record_id,new_values,description,ip_address,user_agent) VALUES (:tenant_id,:branch_id,:user_id,:role_id,'Branch Management',:action_key,'branches',:record_id,:new_values,:description,:ip_address,:user_agent)");
        $s->execute([
            'tenant_id'=>$tenantId ?: null,'branch_id'=>$branchId ?: null,'user_id'=>$user['user_id'] ?: null,'role_id'=>$user['role_id'] ?: null,
            'action_key'=>$action,'record_id'=>$branchId ?: null,'new_values'=>json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'description'=>'Super Admin branch ' . $action . '.','ip_address'=>$_SERVER['REMOTE_ADDR'] ?? null,'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500),
        ]);
    } catch (Throwable $e) {
        error_log('branches api audit: ' . $e->getMessage());
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) brOut(false, 'Database connection is missing.', ['build'=>SUPER_ADMIN_BRANCH_BUILD], 500);
$user = brRequireSuperAdmin($pdo);
if (!brTable($pdo,'branches') || !brTable($pdo,'tenants')) brOut(false, 'Required branches/tenants table is missing.', ['build'=>SUPER_ADMIN_BRANCH_BUILD], 500);
if (empty($_SESSION['branch_management_csrf']) || !is_string($_SESSION['branch_management_csrf'])) $_SESSION['branch_management_csrf']=bin2hex(random_bytes(32));

try {
    $action = strtolower(trim((string)($_POST['action'] ?? $_GET['action'] ?? 'list')));

    if ($action === 'meta') {
        brOut(true,'Branch metadata loaded.',['schools'=>brSchools($pdo),'csrf_token'=>$_SESSION['branch_management_csrf'],'build'=>SUPER_ADMIN_BRANCH_BUILD]);
    }

    if ($action === 'list') {
        $page=max(1,(int)($_GET['page']??1)); $perPage=max(5,min(100,(int)($_GET['per_page']??10)));
        $search=trim((string)($_GET['search']??'')); $schoolId=max(0,(int)($_GET['school_id']??0));
        $branchType=strtolower(trim((string)($_GET['branch_type']??''))); $status=strtolower(trim((string)($_GET['status']??'')));
        $where=[];$params=[];
        if($search!==''){ $where[]="(b.branch_name LIKE :search OR b.branch_code LIKE :search OR b.phone LIKE :search OR b.address LIKE :search OR t.school_name LIKE :search OR t.tenant_code LIKE :search)"; $params['search']='%'.$search.'%'; }
        if($schoolId>0){$where[]='b.tenant_id=:tenant_id';$params['tenant_id']=$schoolId;}
        if($branchType==='main')$where[]='b.is_main=1'; elseif($branchType==='branch')$where[]='b.is_main=0';
        if(in_array($status,['active','inactive'],true)){$where[]='b.status=:status';$params['status']=$status;}
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';

        $count=$pdo->prepare("SELECT COUNT(*) FROM branches b INNER JOIN tenants t ON t.id=b.tenant_id".$whereSql); $count->execute($params); $total=(int)$count->fetchColumn();
        $lastPage=max(1,(int)ceil($total/$perPage));$page=min($page,$lastPage);$offset=($page-1)*$perPage;

        $userCountSql = (brTable($pdo,'users') && brColumn($pdo,'users','default_branch_id')) ? "(SELECT COUNT(*) FROM users u WHERE u.default_branch_id=b.id)" : "0";
        $studentCountSql = (brTable($pdo,'students') && brColumn($pdo,'students','branch_id')) ? "(SELECT COUNT(*) FROM students s WHERE s.branch_id=b.id)" : "0";
        $sql="SELECT b.id,b.tenant_id,b.branch_code,b.branch_name,b.address,b.phone,b.is_main,b.status,b.created_at,t.tenant_code,t.school_name, {$userCountSql} AS user_count, {$studentCountSql} AS student_count FROM branches b INNER JOIN tenants t ON t.id=b.tenant_id".$whereSql." ORDER BY t.school_name ASC,b.is_main DESC,b.branch_name ASC,b.id ASC LIMIT :limit OFFSET :offset";
        $stmt=$pdo->prepare($sql); foreach($params as $k=>$v)$stmt->bindValue(':'.$k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();$records=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];

        $stats=$pdo->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(is_main=1) main,COUNT(DISTINCT tenant_id) schools FROM branches")->fetch(PDO::FETCH_ASSOC)?:[];
        brOut(true,'Branches loaded successfully.',[
            'records'=>$records,'schools'=>brSchools($pdo),
            'pagination'=>['total'=>$total,'page'=>$page,'last_page'=>$lastPage,'per_page'=>$perPage],
            'stats'=>['total'=>(int)($stats['total']??0),'active'=>(int)($stats['active']??0),'main'=>(int)($stats['main']??0),'schools'=>(int)($stats['schools']??0)],
            'csrf_token'=>$_SESSION['branch_management_csrf'],'build'=>SUPER_ADMIN_BRANCH_BUILD,
        ]);
    }

    if ($action === 'get') {
        $id=max(0,(int)($_GET['id']??0)); if($id<=0)brOut(false,'A valid Branch ID is required.',[],422);
        $userCountSql = (brTable($pdo,'users') && brColumn($pdo,'users','default_branch_id')) ? "(SELECT COUNT(*) FROM users u WHERE u.default_branch_id=b.id)" : "0";
        $studentCountSql = (brTable($pdo,'students') && brColumn($pdo,'students','branch_id')) ? "(SELECT COUNT(*) FROM students s WHERE s.branch_id=b.id)" : "0";
        $s=$pdo->prepare("SELECT b.*,t.tenant_code,t.school_name,{$userCountSql} AS user_count,{$studentCountSql} AS student_count FROM branches b INNER JOIN tenants t ON t.id=b.tenant_id WHERE b.id=? LIMIT 1");$s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if(!$r)brOut(false,'Branch record was not found.',[],404);
        brOut(true,'Branch loaded successfully.',['branch'=>$r,'csrf_token'=>$_SESSION['branch_management_csrf'],'build'=>SUPER_ADMIN_BRANCH_BUILD]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') brOut(false,'Method not allowed.',[],405);
    brCsrf($_POST);

    if ($action === 'save') {
        $data = brValidate($pdo, $_POST);

        /*
         * Existing branch ownership cannot be moved to another School.
         * Moving the branch would mix/orphan branch-owned operational data.
         */
        if ($data['id'] > 0) {
            $existingSchool = $pdo->prepare(
                "SELECT tenant_id
                 FROM branches
                 WHERE id = ?
                 LIMIT 1"
            );

            $existingSchool->execute([
                $data['id']
            ]);

            $existingTenantId =
                (int)$existingSchool
                    ->fetchColumn();

            if ($existingTenantId <= 0) {
                throw new RuntimeException(
                    'Branch record was not found.'
                );
            }

            if (
                $existingTenantId
                !== (int)$data['tenant_id']
            ) {
                throw new InvalidArgumentException(
                    'An existing Branch cannot be moved to another School.'
                );
            }
        }

        if ($data['branch_code'] === '') {
            $data['branch_code'] =
                $data['is_main'] === 1
                    ? 'MAIN'
                    : brGenerateCode(
                        $pdo,
                        $data['tenant_id']
                    );
        }

        $dup = $pdo->prepare(
            "SELECT id
             FROM branches
             WHERE tenant_id = ?
               AND branch_code = ?
               AND id <> ?
             LIMIT 1"
        );

        $dup->execute([
            $data['tenant_id'],
            $data['branch_code'],
            $data['id']
        ]);

        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException(
                'Branch Code already exists for this school.'
            );
        }

        $pdo->beginTransaction();

        try {
            $legacyRowsPinned = 0;

            if ($data['id'] <= 0) {
                /*
                 * Existing legacy/unassigned operational rows belong to the
                 * old Main Branch, never to the newly created Branch.
                 */
                $legacyRowsPinned =
                    brPinLegacyRowsToMainBranch(
                        $pdo,
                        (int)$data['tenant_id']
                    );
            }

            if ($data['is_main'] === 1) {
                $clear = $pdo->prepare(
                    "UPDATE branches
                     SET is_main = 0
                     WHERE tenant_id = ?
                       AND id <> ?"
                );

                $clear->execute([
                    $data['tenant_id'],
                    $data['id']
                ]);
            }

            if ($data['id'] > 0) {
                $s = $pdo->prepare(
                    "UPDATE branches
                     SET branch_code = :branch_code,
                         branch_name = :branch_name,
                         address = :address,
                         phone = :phone,
                         is_main = :is_main,
                         status = :status
                     WHERE id = :id
                       AND tenant_id = :tenant_id"
                );

                $s->execute([
                    'tenant_id' =>
                        $data['tenant_id'],
                    'branch_code' =>
                        $data['branch_code'],
                    'branch_name' =>
                        $data['branch_name'],
                    'address' =>
                        $data['address'] ?: null,
                    'phone' =>
                        $data['phone'] ?: null,
                    'is_main' =>
                        $data['is_main'],
                    'status' =>
                        $data['status'],
                    'id' =>
                        $data['id']
                ]);

                $branchId = $data['id'];
                $verb = 'updated';
            } else {
                $s = $pdo->prepare(
                    "INSERT INTO branches
                        (
                            tenant_id,
                            branch_code,
                            branch_name,
                            address,
                            phone,
                            is_main,
                            status,
                            created_at
                        )
                     VALUES
                        (
                            :tenant_id,
                            :branch_code,
                            :branch_name,
                            :address,
                            :phone,
                            :is_main,
                            :status,
                            CURRENT_TIMESTAMP
                        )"
                );

                $s->execute([
                    'tenant_id' =>
                        $data['tenant_id'],
                    'branch_code' =>
                        $data['branch_code'],
                    'branch_name' =>
                        $data['branch_name'],
                    'address' =>
                        $data['address'] ?: null,
                    'phone' =>
                        $data['phone'] ?: null,
                    'is_main' =>
                        $data['is_main'],
                    'status' =>
                        $data['status']
                ]);

                $branchId =
                    (int)$pdo->lastInsertId();

                $verb = 'created';

                /*
                 * School Admin gets access to the new Branch.
                 * Branch Admins and normal users are not auto-assigned.
                 */
                if (
                    brTable(
                        $pdo,
                        'user_branch_access'
                    )
                    && brTable(
                        $pdo,
                        'users'
                    )
                    && brTable(
                        $pdo,
                        'roles'
                    )
                ) {
                    $grant = $pdo->prepare(
                        "INSERT INTO user_branch_access
                            (
                                user_id,
                                branch_id,
                                can_access
                            )
                         SELECT
                            u.id,
                            :branch_id,
                            1
                         FROM users u
                         INNER JOIN roles r
                            ON r.id = u.role_id
                           AND r.tenant_id = u.tenant_id
                         WHERE u.tenant_id = :tenant_id
                           AND u.status = 'active'
                           AND r.status = 'active'
                           AND r.role_key IN
                               (
                                   'school_admin',
                                   'school_administrator',
                                   'school-administrator'
                               )
                         ON DUPLICATE KEY UPDATE
                            can_access = 1"
                    );

                    $grant->execute([
                        'branch_id' =>
                            $branchId,
                        'tenant_id' =>
                            $data['tenant_id']
                    ]);
                }
            }

            $pdo->commit();

            brAudit(
                $pdo,
                $user,
                $verb,
                $branchId,
                $data
            );

            brOut(
                true,
                'Branch ' . $verb . ' successfully.',
                [
                    'id' => $branchId,
                    'message' =>
                        'Branch '
                        . $verb
                        . ' successfully.',
                    'legacy_rows_pinned_to_main_branch' =>
                        $legacyRowsPinned,
                    'isolation_mode' =>
                        'strict_branch',
                    'csrf_token' =>
                        $_SESSION[
                            'branch_management_csrf'
                        ],
                    'build' =>
                        SUPER_ADMIN_BRANCH_BUILD
                ]
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    if ($action === 'delete') {
        $id=max(0,(int)($_POST['id']??0));if($id<=0)throw new InvalidArgumentException('A valid Branch ID is required.');
        $s=$pdo->prepare("SELECT * FROM branches WHERE id=? LIMIT 1");$s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)brOut(false,'Branch record was not found.',[],404);
        if((int)$r['is_main']===1)throw new InvalidArgumentException('Main branch cannot be deleted. Assign another main branch first.');
        $usage=brUsage($pdo,$id);if($usage['users']>0||$usage['students']>0)throw new InvalidArgumentException('This branch is linked to users or students and cannot be deleted. Set it inactive instead.');
        $pdo->beginTransaction();try{$d=$pdo->prepare("DELETE FROM branches WHERE id=?");$d->execute([$id]);$pdo->commit();brAudit($pdo,$user,'deleted',$id,$r);brOut(true,'Branch deleted successfully.',['message'=>'Branch deleted successfully.','csrf_token'=>$_SESSION['branch_management_csrf'],'build'=>SUPER_ADMIN_BRANCH_BUILD]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    brOut(false,'Invalid request.',[],400);
} catch (InvalidArgumentException $e) {
    brOut(false,$e->getMessage(),['build'=>SUPER_ADMIN_BRANCH_BUILD],422);
} catch (Throwable $e) {
    error_log('super-admin branches api: '.$e->getMessage());
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $message=(str_contains($host,'localhost')||str_contains($host,'127.0.0.1')) ? 'Branch request failed: '.$e->getMessage() : 'Unable to complete the branch request.';
    brOut(false,$message,['build'=>SUPER_ADMIN_BRANCH_BUILD],500);
}

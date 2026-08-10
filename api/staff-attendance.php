<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

function saOut(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function saInput(): array
{
    $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($type, 'application/json')) {
        $data = json_decode((string)file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}
function saScope(): array
{
    $u = function_exists('current_user') ? current_user() : [];
    $u = is_array($u) ? $u : [];
    return [
        'tenant_id'=>(int)($u['tenant_id'] ?? $u['school_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0),
        'branch_id'=>(int)($u['branch_id'] ?? $u['default_branch_id'] ?? $_SESSION['branch_id'] ?? 0),
        'user_id'=>(int)($u['id'] ?? $u['user_id'] ?? $_SESSION['user_id'] ?? 0),
        'role_id'=>(int)($u['role_id'] ?? $_SESSION['role_id'] ?? 0),
    ];
}
function saCsrf(array $input): void
{
    $session = (string)($_SESSION['staff_attendance_csrf'] ?? '');
    $request = (string)($input['csrf_token'] ?? '');
    if ($session === '' || $request === '' || !hash_equals($session, $request)) {
        saOut(false, 'Invalid or expired CSRF token. Refresh the page.', [], 419);
    }
}
function saTable(PDO $pdo, string $table): bool
{
    $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table_name');
    $s->execute(['table_name'=>$table]);
    return (int)$s->fetchColumn()>0;
}
function saColumn(PDO $pdo, string $table, string $column): bool
{
    $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table_name AND column_name=:column_name');
    $s->execute(['table_name'=>$table,'column_name'=>$column]);
    return (int)$s->fetchColumn()>0;
}
function saIndex(PDO $pdo, string $table, string $index): bool
{
    $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table_name AND index_name=:index_name');
    $s->execute(['table_name'=>$table,'index_name'=>$index]);
    return (int)$s->fetchColumn()>0;
}
function saUpgradeAttendance(PDO $pdo): void
{
    $columns = [
        'tenant_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER id",
        'branch_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER tenant_id",
        'staff_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER branch_id",
        'attendance_date' => "DATE NULL AFTER staff_id",
        'attendance_status' => "ENUM('present','absent','late','half_day') NOT NULL DEFAULT 'present' AFTER attendance_date",
        'punch_in' => "DATETIME DEFAULT NULL AFTER attendance_status",
        'punch_out' => "DATETIME DEFAULT NULL AFTER punch_in",
        'working_minutes' => "INT NOT NULL DEFAULT 0 AFTER punch_out",
        'punch_in_latitude' => "DECIMAL(10,7) DEFAULT NULL AFTER working_minutes",
        'punch_in_longitude' => "DECIMAL(10,7) DEFAULT NULL AFTER punch_in_latitude",
        'punch_in_accuracy' => "DECIMAL(10,2) DEFAULT NULL AFTER punch_in_longitude",
        'punch_in_distance_meters' => "DECIMAL(12,2) DEFAULT NULL AFTER punch_in_accuracy",
        'punch_out_latitude' => "DECIMAL(10,7) DEFAULT NULL AFTER punch_in_distance_meters",
        'punch_out_longitude' => "DECIMAL(10,7) DEFAULT NULL AFTER punch_out_latitude",
        'punch_out_accuracy' => "DECIMAL(10,2) DEFAULT NULL AFTER punch_out_longitude",
        'punch_out_distance_meters' => "DECIMAL(12,2) DEFAULT NULL AFTER punch_out_accuracy",
        'source' => "ENUM('manual','gps','bulk') NOT NULL DEFAULT 'manual' AFTER punch_out_distance_meters",
        'remarks' => "VARCHAR(255) DEFAULT NULL AFTER source",
        'marked_by' => "BIGINT UNSIGNED DEFAULT NULL AFTER remarks",
        'updated_by' => "BIGINT UNSIGNED DEFAULT NULL AFTER marked_by",
        'created_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_by",
        'updated_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];
    foreach ($columns as $name => $definition) {
        if (!saColumn($pdo, 'staff_attendance', $name)) {
            $pdo->exec("ALTER TABLE staff_attendance ADD COLUMN `$name` $definition");
        }
    }

    // Preserve attendance values from older schemas that used `status`.
    if (saColumn($pdo, 'staff_attendance', 'status')) {
        $pdo->exec("UPDATE staff_attendance SET attendance_status = CASE LOWER(COALESCE(`status`,'')) WHEN 'absent' THEN 'absent' WHEN 'late' THEN 'late' WHEN 'half_day' THEN 'half_day' WHEN 'half day' THEN 'half_day' WHEN 'halfday' THEN 'half_day' ELSE 'present' END WHERE attendance_status IS NULL OR attendance_status='present'");
    }
    if (saColumn($pdo, 'staff_attendance', 'date')) {
        $pdo->exec("UPDATE staff_attendance SET attendance_date=`date` WHERE attendance_date IS NULL");
    }

    if (!saIndex($pdo, 'staff_attendance', 'uk_staff_attendance_day')) {
        // Remove accidental duplicate legacy rows before creating the unique key.
        $pdo->exec("DELETE older FROM staff_attendance older INNER JOIN staff_attendance newer ON newer.tenant_id=older.tenant_id AND newer.staff_id=older.staff_id AND newer.attendance_date=older.attendance_date AND newer.id>older.id WHERE older.attendance_date IS NOT NULL");
        $pdo->exec("ALTER TABLE staff_attendance ADD UNIQUE KEY uk_staff_attendance_day(tenant_id,staff_id,attendance_date)");
    }
    if (!saIndex($pdo, 'staff_attendance', 'idx_staff_att_date')) {
        $pdo->exec("ALTER TABLE staff_attendance ADD KEY idx_staff_att_date(tenant_id,branch_id,attendance_date,attendance_status)");
    }
    if (!saIndex($pdo, 'staff_attendance', 'idx_staff_att_staff')) {
        $pdo->exec("ALTER TABLE staff_attendance ADD KEY idx_staff_att_staff(staff_id,attendance_date)");
    }
}
function saEnsure(PDO $pdo): void
{
    foreach (['staff_members','staff_departments','staff_designations'] as $table) {
        if (!saTable($pdo,$table)) throw new RuntimeException('Missing required table: '.$table.'. Install Staff Management first.');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_attendance_settings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        location_enabled TINYINT(1) NOT NULL DEFAULT 0,
        school_latitude DECIMAL(10,7) DEFAULT NULL,
        school_longitude DECIMAL(10,7) DEFAULT NULL,
        allowed_radius_meters INT NOT NULL DEFAULT 100,
        office_start_time TIME NOT NULL DEFAULT '09:00:00',
        late_after_time TIME NOT NULL DEFAULT '09:15:00',
        half_day_hours DECIMAL(5,2) NOT NULL DEFAULT 4.00,
        full_day_hours DECIMAL(5,2) NOT NULL DEFAULT 7.00,
        updated_by BIGINT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id), UNIQUE KEY uk_att_setting(tenant_id,branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_attendance (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NOT NULL,
        staff_id BIGINT UNSIGNED NOT NULL,
        attendance_date DATE NOT NULL,
        attendance_status ENUM('present','absent','late','half_day') NOT NULL DEFAULT 'present',
        punch_in DATETIME DEFAULT NULL,
        punch_out DATETIME DEFAULT NULL,
        working_minutes INT NOT NULL DEFAULT 0,
        punch_in_latitude DECIMAL(10,7) DEFAULT NULL,
        punch_in_longitude DECIMAL(10,7) DEFAULT NULL,
        punch_in_accuracy DECIMAL(10,2) DEFAULT NULL,
        punch_in_distance_meters DECIMAL(12,2) DEFAULT NULL,
        punch_out_latitude DECIMAL(10,7) DEFAULT NULL,
        punch_out_longitude DECIMAL(10,7) DEFAULT NULL,
        punch_out_accuracy DECIMAL(10,2) DEFAULT NULL,
        punch_out_distance_meters DECIMAL(12,2) DEFAULT NULL,
        source ENUM('manual','gps','bulk') NOT NULL DEFAULT 'manual',
        remarks VARCHAR(255) DEFAULT NULL,
        marked_by BIGINT UNSIGNED DEFAULT NULL,
        updated_by BIGINT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uk_staff_attendance_day(tenant_id,staff_id,attendance_date),
        KEY idx_staff_att_date(tenant_id,branch_id,attendance_date,attendance_status),
        KEY idx_staff_att_staff(staff_id,attendance_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    saUpgradeAttendance($pdo);
}
function saDate(string $value): string
{
    $d=DateTimeImmutable::createFromFormat('Y-m-d',$value); $e=DateTimeImmutable::getLastErrors();
    if(!$d || (is_array($e) && (($e['warning_count']??0)>0 || ($e['error_count']??0)>0)) || $d->format('Y-m-d')!==$value) throw new InvalidArgumentException('Attendance Date is invalid.');
    return $value;
}
function saTime(?string $value, string $label): ?string
{
    $value=trim((string)$value); if($value==='') return null;
    foreach(['H:i','H:i:s'] as $format){$d=DateTimeImmutable::createFromFormat($format,$value);if($d&&$d->format($format)===$value)return $d->format('H:i:s');}
    throw new InvalidArgumentException($label.' is invalid.');
}
function saSettings(PDO $pdo,array $scope): array
{
    $branch=max(0,$scope['branch_id']);
    $s=$pdo->prepare("INSERT INTO staff_attendance_settings(tenant_id,branch_id) VALUES(:tenant_id,:branch_id) ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id)");
    $s->execute(['tenant_id'=>$scope['tenant_id'],'branch_id'=>$branch]);
    $q=$pdo->prepare('SELECT * FROM staff_attendance_settings WHERE tenant_id=:tenant_id AND branch_id=:branch_id LIMIT 1');
    $q->execute(['tenant_id'=>$scope['tenant_id'],'branch_id'=>$branch]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}
function saCanEdit(PDO $pdo,array $scope): bool
{
    /*
     * Resolve the role from the users table instead of trusting only the
     * session. Some login implementations do not store role_id in session,
     * which previously caused valid administrators to be denied.
     */
    $roleId=(int)($scope['role_id']??0);
    if((int)($scope['user_id']??0)>0 && saTable($pdo,'users')){
        $u=$pdo->prepare("SELECT role_id FROM users WHERE id=:user_id AND tenant_id=:tenant_id LIMIT 1");
        $u->execute(['user_id'=>$scope['user_id'],'tenant_id'=>$scope['tenant_id']]);
        $dbRoleId=(int)$u->fetchColumn();
        if($dbRoleId>0)$roleId=$dbRoleId;
    }

    // Preserve compatibility with systems that do not use role records.
    if($roleId<=0)return true;

    $roleKey='';
    if(saTable($pdo,'roles')){
        $r=$pdo->prepare("SELECT role_key FROM roles WHERE id=:role_id AND (tenant_id=:tenant_id OR tenant_id IS NULL) AND status='active' LIMIT 1");
        $r->execute(['role_id'=>$roleId,'tenant_id'=>$scope['tenant_id']]);
        $roleKey=strtolower(trim((string)$r->fetchColumn()));
    }

    // Administrative roles always have edit permission.
    if(in_array($roleKey,['super_admin','admin','administrator','principal','hr'],true))return true;

    /*
     * For all other roles, honour the School ERP page permission table.
     * Support both the dedicated staff-attendance page and older systems
     * where the permission was assigned to the main attendance page.
     */
    if(!saTable($pdo,'role_page_permissions') || !saTable($pdo,'app_pages'))return false;

    $p=$pdo->prepare("SELECT COALESCE(rpp.can_edit,0)
        FROM role_page_permissions rpp
        INNER JOIN app_pages ap ON ap.id=rpp.page_id
        WHERE rpp.role_id=:role_id
          AND ap.page_key IN('staff_attendance','staff-attendance','attendance')
          AND ap.is_active=1
        ORDER BY CASE ap.page_key
            WHEN 'staff_attendance' THEN 1
            WHEN 'staff-attendance' THEN 2
            ELSE 3 END
        LIMIT 1");
    $p->execute(['role_id'=>$roleId]);
    return (int)$p->fetchColumn()===1;
}
function saDistance(float $lat1,float $lon1,float $lat2,float $lon2): float
{
    $r=6371000.0;$p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=deg2rad($lat2-$lat1);$dl=deg2rad($lon2-$lon1);
    $a=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;
    return $r*2*atan2(sqrt($a),sqrt(1-$a));
}
function saGps(array $input,array $settings): array
{
    if((int)($settings['location_enabled']??0)!==1) return ['lat'=>null,'lng'=>null,'accuracy'=>null,'distance'=>null];
    $lat=filter_var($input['latitude']??null,FILTER_VALIDATE_FLOAT);$lng=filter_var($input['longitude']??null,FILTER_VALIDATE_FLOAT);$accuracy=filter_var($input['accuracy']??null,FILTER_VALIDATE_FLOAT);
    $slat=isset($settings['school_latitude'])?(float)$settings['school_latitude']:0;$slng=isset($settings['school_longitude'])?(float)$settings['school_longitude']:0;
    if($lat===false||$lng===false||$slat===0.0||$slng===0.0) throw new InvalidArgumentException('GPS location is required and School Coordinates must be configured.');
    if($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) throw new InvalidArgumentException('Invalid GPS coordinates.');
    $distance=saDistance((float)$lat,(float)$lng,$slat,$slng);$radius=max(10,(int)($settings['allowed_radius_meters']??100));
    if($distance>$radius) throw new InvalidArgumentException('Punch denied. Current location is '.round($distance).' metres from school; allowed radius is '.$radius.' metres.');
    return ['lat'=>(float)$lat,'lng'=>(float)$lng,'accuracy'=>$accuracy===false?null:(float)$accuracy,'distance'=>round($distance,2)];
}
function saStaffTypeSql(): string
{
    return "CASE WHEN UPPER(COALESCE(d.department_code,''))='TEACHING' OR UPPER(COALESCE(ds.designation_code,'')) IN('TEACHER','HEAD_TEACHER','PRINCIPAL','VICE_PRINCIPAL') THEN 'teaching' ELSE 'non_teaching' END";
}
function saList(PDO $pdo,array $scope,array $filters): array
{
    $date=saDate((string)($filters['attendance_date']??date('Y-m-d')));$where=['s.tenant_id=:tenant_id','s.deleted_at IS NULL',"s.status='active'"];$params=['tenant_id'=>$scope['tenant_id'],'attendance_date'=>$date];
    if($scope['branch_id']>0){$where[]='s.branch_id=:branch_id';$params['branch_id']=$scope['branch_id'];}
    $search=trim((string)($filters['search']??''));if($search!==''){$where[]="(s.staff_code LIKE :q OR s.first_name LIKE :q OR s.last_name LIKE :q OR CONCAT_WS(' ',s.first_name,s.last_name) LIKE :q OR s.mobile LIKE :q)";$params['q']='%'.$search.'%';}
    $type=strtolower((string)($filters['staff_type']??'all'));$typeSql=saStaffTypeSql();if(in_array($type,['teaching','non_teaching'],true))$where[]="$typeSql=:staff_type";$params+=in_array($type,['teaching','non_teaching'],true)?['staff_type'=>$type]:[];
    $status=strtolower((string)($filters['attendance_status']??'all'));if(in_array($status,['present','absent','late','half_day'],true)){$where[]='a.attendance_status=:att_status';$params['att_status']=$status;}
    $sql="SELECT s.id,s.staff_code,TRIM(CONCAT(s.first_name,' ',COALESCE(s.last_name,''))) staff_name,s.mobile,d.department_name,ds.designation_name,$typeSql staff_type,a.id attendance_id,a.attendance_status,a.punch_in,a.punch_out,a.working_minutes,a.source,a.remarks FROM staff_members s LEFT JOIN staff_departments d ON d.id=s.department_id AND d.tenant_id=s.tenant_id LEFT JOIN staff_designations ds ON ds.id=s.designation_id AND ds.tenant_id=s.tenant_id LEFT JOIN staff_attendance a ON a.staff_id=s.id AND a.tenant_id=s.tenant_id AND a.attendance_date=:attendance_date WHERE ".implode(' AND ',$where)." ORDER BY staff_type DESC,s.first_name,s.last_name";
    $q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$r){$r['attendance_status']=$r['attendance_status']?:'not_marked';$r['working_hours']=sprintf('%02d:%02d',intdiv((int)$r['working_minutes'],60),(int)$r['working_minutes']%60);$r['punch_in_time']=$r['punch_in']?date('h:i A',strtotime($r['punch_in'])):'';$r['punch_out_time']=$r['punch_out']?date('h:i A',strtotime($r['punch_out'])):'';}unset($r);
    $stats=['total'=>count($rows),'present'=>0,'absent'=>0,'late'=>0,'half_day'=>0,'not_marked'=>0];foreach($rows as $r)$stats[$r['attendance_status']]++;
    return ['records'=>$rows,'stats'=>$stats,'attendance_date'=>$date];
}
function saSaveOne(PDO $pdo,array $scope,array $input): array
{
    if(!saCanEdit($pdo,$scope)) throw new RuntimeException('You do not have permission to edit Staff Attendance.',403);
    $staffId=(int)($input['staff_id']??0);$date=saDate((string)($input['attendance_date']??date('Y-m-d')));$status=strtolower((string)($input['attendance_status']??''));
    if($staffId<=0||!in_array($status,['present','absent','late','half_day'],true)) throw new InvalidArgumentException('Staff and Attendance Status are required.');
    $check=$pdo->prepare("SELECT id,branch_id FROM staff_members WHERE id=:id AND tenant_id=:tenant_id AND deleted_at IS NULL AND status='active'".($scope['branch_id']>0?' AND branch_id=:branch_id':'').' LIMIT 1');$p=['id'=>$staffId,'tenant_id'=>$scope['tenant_id']];if($scope['branch_id']>0)$p['branch_id']=$scope['branch_id'];$check->execute($p);$staff=$check->fetch(PDO::FETCH_ASSOC);if(!$staff)throw new InvalidArgumentException('Selected Staff is invalid or inactive.');
    $in=saTime($input['punch_in_time']??null,'Punch In Time');$out=saTime($input['punch_out_time']??null,'Punch Out Time');
    $punchIn=$in?"$date $in":null;$punchOut=$out?"$date $out":null;if($punchIn&&$punchOut&&strtotime($punchOut)<strtotime($punchIn))throw new InvalidArgumentException('Punch Out Time cannot be before Punch In Time.');
    $minutes=($punchIn&&$punchOut)?max(0,(int)round((strtotime($punchOut)-strtotime($punchIn))/60)):0;
    $pdo->beginTransaction();
    try{
        $sql="INSERT INTO staff_attendance(tenant_id,branch_id,staff_id,attendance_date,attendance_status,punch_in,punch_out,working_minutes,source,remarks,marked_by,updated_by) VALUES(:tenant_id,:branch_id,:staff_id,:attendance_date,:attendance_status,:punch_in,:punch_out,:working_minutes,'manual',:remarks,:marked_by,:updated_by) ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status),punch_in=VALUES(punch_in),punch_out=VALUES(punch_out),working_minutes=VALUES(working_minutes),source='manual',remarks=VALUES(remarks),updated_by=VALUES(updated_by)";
        $s=$pdo->prepare($sql);$s->execute(['tenant_id'=>$scope['tenant_id'],'branch_id'=>(int)$staff['branch_id'],'staff_id'=>$staffId,'attendance_date'=>$date,'attendance_status'=>$status,'punch_in'=>$punchIn,'punch_out'=>$punchOut,'working_minutes'=>$minutes,'remarks'=>trim((string)($input['remarks']??''))?:null,'marked_by'=>$scope['user_id'],'updated_by'=>$scope['user_id']]);
        $pdo->commit();return ['staff_id'=>$staffId];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function saBulk(PDO $pdo,array $scope,array $input): int
{
    if(!saCanEdit($pdo,$scope)) throw new RuntimeException('You do not have permission to edit Staff Attendance.',403);
    $date=saDate((string)($input['attendance_date']??date('Y-m-d')));$status=strtolower((string)($input['attendance_status']??''));if(!in_array($status,['present','absent'],true))throw new InvalidArgumentException('Bulk Status is invalid.');
    $type=strtolower((string)($input['staff_type']??'all'));$where=['s.tenant_id=:tenant_id','s.deleted_at IS NULL',"s.status='active'"];$params=['tenant_id'=>$scope['tenant_id']];if($scope['branch_id']>0){$where[]='s.branch_id=:branch_id';$params['branch_id']=$scope['branch_id'];}
    $typeSql=saStaffTypeSql();if(in_array($type,['teaching','non_teaching'],true)){$where[]="$typeSql=:staff_type";$params['staff_type']=$type;}
    $q=$pdo->prepare("SELECT s.id,s.branch_id FROM staff_members s LEFT JOIN staff_departments d ON d.id=s.department_id AND d.tenant_id=s.tenant_id LEFT JOIN staff_designations ds ON ds.id=s.designation_id AND ds.tenant_id=s.tenant_id WHERE ".implode(' AND ',$where));$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $pdo->beginTransaction();try{$s=$pdo->prepare("INSERT INTO staff_attendance(tenant_id,branch_id,staff_id,attendance_date,attendance_status,source,marked_by,updated_by) VALUES(:tenant_id,:branch_id,:staff_id,:attendance_date,:attendance_status,'bulk',:marked_by,:updated_by) ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status),source='bulk',updated_by=VALUES(updated_by)");foreach($rows as $r)$s->execute(['tenant_id'=>$scope['tenant_id'],'branch_id'=>$r['branch_id'],'staff_id'=>$r['id'],'attendance_date'=>$date,'attendance_status'=>$status,'marked_by'=>$scope['user_id'],'updated_by'=>$scope['user_id']]);$pdo->commit();return count($rows);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function saPunch(PDO $pdo,array $scope,array $input,string $mode): array
{
    $staffId=(int)($input['staff_id']??0);if($staffId<=0)throw new InvalidArgumentException('Staff is required.');$settings=saSettings($pdo,$scope);$gps=saGps($input,$settings);$now=new DateTimeImmutable('now');$date=$now->format('Y-m-d');
    $check=$pdo->prepare("SELECT id,branch_id FROM staff_members WHERE id=:id AND tenant_id=:tenant_id AND deleted_at IS NULL AND status='active'".($scope['branch_id']>0?' AND branch_id=:branch_id':'').' LIMIT 1');$p=['id'=>$staffId,'tenant_id'=>$scope['tenant_id']];if($scope['branch_id']>0)$p['branch_id']=$scope['branch_id'];$check->execute($p);$staff=$check->fetch(PDO::FETCH_ASSOC);if(!$staff)throw new InvalidArgumentException('Selected Staff is invalid.');
    $pdo->beginTransaction();try{
        $lock=$pdo->prepare('SELECT * FROM staff_attendance WHERE tenant_id=:tenant_id AND staff_id=:staff_id AND attendance_date=:attendance_date FOR UPDATE');$lock->execute(['tenant_id'=>$scope['tenant_id'],'staff_id'=>$staffId,'attendance_date'=>$date]);$row=$lock->fetch(PDO::FETCH_ASSOC);
        if($mode==='in'){
            if($row && $row['punch_in'])throw new InvalidArgumentException('Punch In is already recorded for today.');$lateAfter=(string)($settings['late_after_time']??'09:15:00');$status=$now->format('H:i:s')>$lateAfter?'late':'present';
            $s=$pdo->prepare("INSERT INTO staff_attendance(tenant_id,branch_id,staff_id,attendance_date,attendance_status,punch_in,punch_in_latitude,punch_in_longitude,punch_in_accuracy,punch_in_distance_meters,source,marked_by,updated_by) VALUES(:tenant_id,:branch_id,:staff_id,:attendance_date,:attendance_status,:punch_in,:lat,:lng,:accuracy,:distance,'gps',:marked_by,:updated_by) ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status),punch_in=VALUES(punch_in),punch_in_latitude=VALUES(punch_in_latitude),punch_in_longitude=VALUES(punch_in_longitude),punch_in_accuracy=VALUES(punch_in_accuracy),punch_in_distance_meters=VALUES(punch_in_distance_meters),source='gps',updated_by=VALUES(updated_by)");
            $s->execute(['tenant_id'=>$scope['tenant_id'],'branch_id'=>$staff['branch_id'],'staff_id'=>$staffId,'attendance_date'=>$date,'attendance_status'=>$status,'punch_in'=>$now->format('Y-m-d H:i:s'),'lat'=>$gps['lat'],'lng'=>$gps['lng'],'accuracy'=>$gps['accuracy'],'distance'=>$gps['distance'],'marked_by'=>$scope['user_id'],'updated_by'=>$scope['user_id']]);
        }else{
            if(!$row||!$row['punch_in'])throw new InvalidArgumentException('Punch In must be completed first.');if($row['punch_out'])throw new InvalidArgumentException('Punch Out is already recorded for today.');$minutes=max(0,(int)round(($now->getTimestamp()-strtotime($row['punch_in']))/60));$half=(float)($settings['half_day_hours']??4)*60;$status=$minutes<$half?'half_day':($row['attendance_status']==='late'?'late':'present');
            $s=$pdo->prepare("UPDATE staff_attendance SET punch_out=:punch_out,working_minutes=:minutes,attendance_status=:status,punch_out_latitude=:lat,punch_out_longitude=:lng,punch_out_accuracy=:accuracy,punch_out_distance_meters=:distance,source='gps',updated_by=:updated_by WHERE id=:id AND tenant_id=:tenant_id");
            $s->execute(['punch_out'=>$now->format('Y-m-d H:i:s'),'minutes'=>$minutes,'status'=>$status,'lat'=>$gps['lat'],'lng'=>$gps['lng'],'accuracy'=>$gps['accuracy'],'distance'=>$gps['distance'],'updated_by'=>$scope['user_id'],'id'=>$row['id'],'tenant_id'=>$scope['tenant_id']]);
        }
        $pdo->commit();return ['time'=>$now->format('h:i A'),'distance'=>$gps['distance']];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

if(!isset($pdo)||!$pdo instanceof PDO)saOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();if(empty($_SESSION['staff_attendance_csrf']))$_SESSION['staff_attendance_csrf']=bin2hex(random_bytes(32));
$scope=saScope();if($scope['tenant_id']<=0||$scope['user_id']<=0)saOut(false,'Tenant or user session is missing.',[],401);
try{saEnsure($pdo);}catch(Throwable $e){saOut(false,'Unable to initialize Staff Attendance: '.$e->getMessage(),[],500);}
$input=saInput();$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));
try{
    if($action==='meta'){
        $settings=saSettings($pdo,$scope);$departments=$pdo->prepare("SELECT id,department_name FROM staff_departments WHERE tenant_id=:tenant_id AND status='active' ORDER BY display_order,department_name");$departments->execute(['tenant_id'=>$scope['tenant_id']]);
        saOut(true,'Staff Attendance loaded.',['settings'=>$settings,'can_edit'=>saCanEdit($pdo,$scope),'departments'=>$departments->fetchAll(PDO::FETCH_ASSOC),'csrf_token'=>$_SESSION['staff_attendance_csrf']]);
    }
    if($action==='list'||$action==='report')saOut(true,'Staff attendance loaded.',saList($pdo,$scope,array_merge($_GET,$input)));
    if($action==='save'){saCsrf($input);saOut(true,'Attendance saved successfully.',saSaveOne($pdo,$scope,$input));}
    if($action==='bulk'){saCsrf($input);$count=saBulk($pdo,$scope,$input);saOut(true,$count.' staff attendance records updated.',['count'=>$count]);}
    if($action==='punch_in'||$action==='punch_out'){saCsrf($input);$data=saPunch($pdo,$scope,$input,$action==='punch_in'?'in':'out');saOut(true,$action==='punch_in'?'Punch In recorded successfully.':'Punch Out recorded successfully.',$data);}
    if($action==='save_settings'){
        saCsrf($input);if(!saCanEdit($pdo,$scope))throw new RuntimeException('You do not have permission to change Attendance Settings.',403);
        $enabled=!empty($input['location_enabled'])?1:0;$lat=trim((string)($input['school_latitude']??''));$lng=trim((string)($input['school_longitude']??''));$radius=max(10,min(5000,(int)($input['allowed_radius_meters']??100)));$start=saTime($input['office_start_time']??'09:00','Office Start Time');$late=saTime($input['late_after_time']??'09:15','Late Arrival Time');
        if($enabled&&($lat===''||$lng===''||!is_numeric($lat)||!is_numeric($lng)))throw new InvalidArgumentException('Valid School GPS Coordinates are required when Location Attendance is enabled.');
        $s=$pdo->prepare("INSERT INTO staff_attendance_settings(tenant_id,branch_id,location_enabled,school_latitude,school_longitude,allowed_radius_meters,office_start_time,late_after_time,updated_by) VALUES(:tenant_id,:branch_id,:enabled,:lat,:lng,:radius,:start,:late,:updated_by) ON DUPLICATE KEY UPDATE location_enabled=VALUES(location_enabled),school_latitude=VALUES(school_latitude),school_longitude=VALUES(school_longitude),allowed_radius_meters=VALUES(allowed_radius_meters),office_start_time=VALUES(office_start_time),late_after_time=VALUES(late_after_time),updated_by=VALUES(updated_by)");
        $s->execute(['tenant_id'=>$scope['tenant_id'],'branch_id'=>max(0,$scope['branch_id']),'enabled'=>$enabled,'lat'=>$lat===''?null:$lat,'lng'=>$lng===''?null:$lng,'radius'=>$radius,'start'=>$start,'late'=>$late,'updated_by'=>$scope['user_id']]);saOut(true,'Attendance settings saved successfully.',[]);
    }
    if($action==='export_excel'){
        $result=saList($pdo,$scope,$_GET);while(ob_get_level()>0)ob_end_clean();header('Content-Type: application/vnd.ms-excel; charset=utf-8');header('Content-Disposition: attachment; filename="staff-attendance-'.date('Ymd-His').'.xls"');echo "\xEF\xBB\xBF";echo '<table border="1"><tr><th>Date</th><th>Staff ID</th><th>Name</th><th>Type</th><th>Department</th><th>Punch In</th><th>Punch Out</th><th>Working Hours</th><th>Status</th></tr>';foreach($result['records'] as $r)echo '<tr><td>'.htmlspecialchars($result['attendance_date']).'</td><td>'.htmlspecialchars($r['staff_code']).'</td><td>'.htmlspecialchars($r['staff_name']).'</td><td>'.htmlspecialchars($r['staff_type']).'</td><td>'.htmlspecialchars((string)$r['department_name']).'</td><td>'.htmlspecialchars($r['punch_in_time']).'</td><td>'.htmlspecialchars($r['punch_out_time']).'</td><td>'.htmlspecialchars($r['working_hours']).'</td><td>'.htmlspecialchars($r['attendance_status']).'</td></tr>';echo '</table>';exit;
    }
    saOut(false,'Invalid Staff Attendance action.',[],400);
}catch(InvalidArgumentException $e){saOut(false,$e->getMessage(),[],422);}catch(RuntimeException $e){$code=$e->getCode();saOut(false,$e->getMessage(),[],is_int($code)&&$code>=400&&$code<600?$code:500);}catch(PDOException $e){error_log('Staff Attendance DB: '.$e->getMessage());saOut(false,'Database operation failed while processing Staff Attendance.',[],500);}catch(Throwable $e){error_log('Staff Attendance: '.$e->getMessage());saOut(false,'Unable to process Staff Attendance request.',[],500);}

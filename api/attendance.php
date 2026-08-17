<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

/* Build: 2026-08-17-attendance-strict-branch-v3 */

function attendanceJson(bool $success,string $message='',array $data=[],int $status=200): never
{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['success'=>$success,'message'=>$message,'data'=>$data],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    );
    exit;
}

function attendanceInput(): array
{
    $json=json_decode((string)file_get_contents('php://input'),true);
    return is_array($json)?$json:$_POST;
}

function attendanceUser(): array
{
    $user=function_exists('current_user')?current_user():[];
    return is_array($user)?$user:[];
}

function attendanceScope(PDO $pdo): array
{
    if(session_status()!==PHP_SESSION_ACTIVE){
        session_start();
    }

    $user=attendanceUser();

    $tenantId=(int)(
        $user['tenant_id']
        ??$user['school_id']
        ??$_SESSION['tenant_id']
        ??$_SESSION['school_id']
        ??0
    );

    $userId=(int)(
        $user['id']
        ??$user['user_id']
        ??$_SESSION['user_id']
        ??0
    );

    /*
     * IMPORTANT:
     * Branch Settings stores the selected/current Branch in the session.
     * Use that branch before any older value cached in current_user().
     */
    $branchId=0;

    if(function_exists('current_branch_id')){
        try{
            $branchId=(int)current_branch_id();
        }catch(Throwable){
            $branchId=0;
        }
    }

    if($branchId<=0&&function_exists('branch_current_id')){
        try{
            $branchId=(int)branch_current_id();
        }catch(Throwable){
            $branchId=0;
        }
    }

    if($branchId<=0){
        $branchId=(int)(
            $_SESSION['branch_id']
            ??$_SESSION['default_branch_id']
            ??$user['branch_id']
            ??$user['default_branch_id']
            ??0
        );
    }

    if(
        $branchId<=0
        &&$userId>0
        &&tableExists($pdo,'users')
    ){
        try{
            $query=$pdo->prepare(
                "SELECT default_branch_id
                 FROM users
                 WHERE id=:user_id
                   AND tenant_id=:tenant_id
                 LIMIT 1"
            );
            $query->execute([
                'user_id'=>$userId,
                'tenant_id'=>$tenantId,
            ]);
            $branchId=(int)$query->fetchColumn();
        }catch(Throwable){
            $branchId=0;
        }
    }

    if(
        $branchId<=0
        &&$tenantId>0
        &&tableExists($pdo,'branches')
    ){
        $query=$pdo->prepare(
            "SELECT id
             FROM branches
             WHERE tenant_id=:tenant_id
               AND status='active'
             ORDER BY is_main DESC,id ASC
             LIMIT 1"
        );
        $query->execute([
            'tenant_id'=>$tenantId,
        ]);
        $branchId=(int)$query->fetchColumn();
    }

    if($tenantId<=0){
        throw new RuntimeException(
            'School tenant session was not found.',
            401
        );
    }

    if($branchId<=0){
        throw new RuntimeException(
            'Active Branch is required for Attendance.',
            422
        );
    }

    if(tableExists($pdo,'branches')){
        $query=$pdo->prepare(
            "SELECT branch_name
             FROM branches
             WHERE id=:branch_id
               AND tenant_id=:tenant_id
               AND status='active'
             LIMIT 1"
        );
        $query->execute([
            'branch_id'=>$branchId,
            'tenant_id'=>$tenantId,
        ]);

        $branchName=(string)($query->fetchColumn()?:'');

        if($branchName===''){
            throw new RuntimeException(
                'The selected Branch does not belong to this School.',
                403
            );
        }

        $_SESSION['branch_id']=$branchId;
        $_SESSION['default_branch_id']=$branchId;
        $_SESSION['branch_name']=$branchName;
    }

    /*
     * Database branch-isolation triggers use these connection variables.
     */
    try{
        $query=$pdo->prepare(
            "SET @schoolerp_tenant_id=:tenant_id,
                 @schoolerp_branch_id=:branch_id"
        );
        $query->execute([
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
        ]);
    }catch(Throwable){
        /* Keep compatibility with databases without those triggers. */
    }

    return [
        'tenant_id'=>$tenantId,
        'branch_id'=>$branchId,
        'user_id'=>$userId,
    ];
}

function attendanceCan(string $action): bool
{
    if(function_exists('is_super_admin')&&is_super_admin())return true;
    if(!function_exists('has_permission'))return true;

    return has_permission('attendance_management',$action)
        ||has_permission('attendance',$action)
        ||has_permission('student_management',$action);
}

function attendanceCsrf(array $input): void
{
    if(session_status()!==PHP_SESSION_ACTIVE){
        session_start();
    }

    $sessionToken=(string)(
        $_SESSION['attendance_csrf_token']
        ??''
    );

    $requestToken=trim(
        (string)(
            $input['csrf_token']
            ??$_SERVER['HTTP_X_CSRF_TOKEN']
            ??''
        )
    );

    if(
        $sessionToken===''
        ||$requestToken===''
        ||!hash_equals($sessionToken,$requestToken)
    ){
        attendanceJson(
            false,
            'Invalid or expired CSRF token. Refresh the page and try again.',
            [],
            419
        );
    }
}

function tableExists(PDO $pdo,string $table): bool
{
    $query=$pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema=DATABASE()
           AND table_name=:table_name"
    );
    $query->execute(['table_name'=>$table]);
    return (int)$query->fetchColumn()>0;
}

function ensureAttendanceTables(PDO $pdo): void
{
    if(!tableExists($pdo,'student_attendance')){
        $pdo->exec(
            "CREATE TABLE student_attendance(
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NOT NULL,
                academic_year_id BIGINT UNSIGNED NOT NULL,
                student_id BIGINT UNSIGNED NOT NULL,
                attendance_date DATE NOT NULL,
                status ENUM(
                    'present','absent','half_day','late',
                    'leave','on_duty','holiday'
                ) NOT NULL DEFAULT 'present',
                check_in TIME NULL,
                check_out TIME NULL,
                late_minutes INT NOT NULL DEFAULT 0,
                remarks VARCHAR(255) NULL,
                source ENUM('manual','biometric','import','api')
                    NOT NULL DEFAULT 'manual',
                marked_by BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                UNIQUE KEY uk_student_attendance(
                    student_id,attendance_date
                ),
                KEY idx_attendance_tenant_date(
                    tenant_id,attendance_date
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    if(!tableExists($pdo,'student_attendance_logs')){
        $pdo->exec(
            "CREATE TABLE student_attendance_logs(
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                attendance_id BIGINT UNSIGNED NULL,
                student_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                action_name VARCHAR(80) NOT NULL,
                old_values JSON NULL,
                new_values JSON NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY idx_attendance_logs(
                    tenant_id,branch_id,student_id,created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function logAttendance(
    PDO $pdo,
    array $scope,
    string $action,
    ?int $attendanceId,
    ?int $studentId,
    ?array $old,
    ?array $new
): void {
    $query=$pdo->prepare(
        "INSERT INTO student_attendance_logs(
            tenant_id,branch_id,attendance_id,student_id,
            user_id,action_name,old_values,new_values
        ) VALUES(
            :tenant_id,:branch_id,:attendance_id,:student_id,
            :user_id,:action_name,:old_values,:new_values
        )"
    );

    $query->execute([
        'tenant_id'=>$scope['tenant_id'],
        'branch_id'=>$scope['branch_id']?:null,
        'attendance_id'=>$attendanceId,
        'student_id'=>$studentId,
        'user_id'=>$scope['user_id']?:null,
        'action_name'=>$action,
        'old_values'=>$old?json_encode($old):null,
        'new_values'=>$new?json_encode($new):null,
    ]);
}

function studentNameSql(string $alias='s'): string
{
    return "TRIM(CONCAT(
        COALESCE({$alias}.first_name,''),
        CASE
            WHEN COALESCE({$alias}.last_name,'')=''
            THEN ''
            ELSE CONCAT(' ',{$alias}.last_name)
        END
    ))";
}

function attendanceMeta(PDO $pdo,array $scope): array
{
    $years=[];
    $classes=[];
    $sections=[];
    $students=[];

    if(tableExists($pdo,'academic_years')){
        $query=$pdo->prepare(
            "SELECT id,year_name
             FROM academic_years
             WHERE tenant_id=:tenant_id
               AND branch_id=:branch_id
             ORDER BY is_current DESC,start_date DESC,id DESC"
        );
        $query->execute([
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$scope['branch_id'],
        ]);
        $years=$query->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Attendance must use only the classes that were explicitly configured
     * in Class Management for an academic year.
     *
     * Important:
     *   class_management_classes = configured Class Management rows
     *   classes.id                = canonical ID stored in student_enrollments
     *
     * Therefore we use class_management_classes only to decide WHICH classes
     * are visible, but return the matching canonical classes.id to the UI.
     * This prevents old/orphan rows in classes from appearing in Attendance.
     */
    if(
        tableExists($pdo,'classes')
        &&tableExists($pdo,'class_management_classes')
    ){
        $query=$pdo->prepare(
            "SELECT
                c.id,
                c.class_name,
                c.academic_year_id
             FROM classes c
             INNER JOIN (
                SELECT
                    tenant_id,
                    branch_id,
                    academic_year_id,
                    LOWER(TRIM(class_name)) AS class_key,
                    MIN(display_order) AS management_order
                FROM class_management_classes
                WHERE tenant_id=:management_tenant_id
                  AND branch_id=:management_branch_id
                  AND status='active'
                GROUP BY
                    tenant_id,
                    branch_id,
                    academic_year_id,
                    LOWER(TRIM(class_name))
             ) cmc
                ON cmc.tenant_id=c.tenant_id
               AND cmc.branch_id=c.branch_id
               AND cmc.academic_year_id=c.academic_year_id
               AND cmc.class_key=LOWER(TRIM(c.class_name))
             INNER JOIN academic_years ay
                ON ay.id=c.academic_year_id
               AND ay.tenant_id=c.tenant_id
               AND ay.branch_id=c.branch_id
             WHERE c.tenant_id=:class_tenant_id
               AND c.branch_id=:class_branch_id
               AND c.status='active'
             ORDER BY
                c.academic_year_id DESC,
                c.display_order,
                cmc.management_order,
                c.class_name"
        );
        $query->execute([
            'management_tenant_id'=>$scope['tenant_id'],
            'management_branch_id'=>$scope['branch_id'],
            'class_tenant_id'=>$scope['tenant_id'],
            'class_branch_id'=>$scope['branch_id'],
        ]);
        $classes=$query->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Sections are also limited to exact Class Management class/section rows.
     * The IDs returned are canonical sections.id values because attendance and
     * student_enrollments reference the canonical sections table.
     */
    if(
        tableExists($pdo,'sections')
        &&tableExists($pdo,'classes')
        &&tableExists($pdo,'class_management_classes')
    ){
        $query=$pdo->prepare(
            "SELECT
                sec.id,
                sec.class_id,
                sec.section_name,
                c.academic_year_id
             FROM class_management_classes cmc
             INNER JOIN classes c
                ON c.tenant_id=cmc.tenant_id
               AND c.branch_id=cmc.branch_id
               AND c.academic_year_id=cmc.academic_year_id
               AND LOWER(TRIM(c.class_name))
                   =LOWER(TRIM(cmc.class_name))
               AND c.status='active'
             INNER JOIN academic_years ay
                ON ay.id=c.academic_year_id
               AND ay.tenant_id=c.tenant_id
               AND ay.branch_id=c.branch_id
             INNER JOIN sections sec
                ON sec.tenant_id=c.tenant_id
               AND sec.branch_id=c.branch_id
               AND sec.class_id=c.id
               AND LOWER(TRIM(sec.section_name))
                   =LOWER(TRIM(cmc.section_name))
               AND sec.status='active'
             WHERE cmc.tenant_id=:tenant_id
               AND cmc.branch_id=:branch_id
               AND cmc.status='active'
             ORDER BY
                c.academic_year_id DESC,
                c.display_order,
                c.class_name,
                sec.section_name"
        );
        $query->execute([
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$scope['branch_id'],
        ]);
        $sections=$query->fetchAll(PDO::FETCH_ASSOC);
    }

    if(
        tableExists($pdo,'students')
        &&tableExists($pdo,'student_enrollments')
    ){
        $nameSql=studentNameSql('s');

        $query=$pdo->prepare(
            "SELECT
                s.id,
                s.admission_no AS admission_number,
                {$nameSql} AS student_name,
                e.academic_year_id,
                e.class_id,
                c.class_name,
                e.section_id,
                sec.section_name
             FROM students s
             INNER JOIN student_enrollments e
                ON e.student_id=s.id
               AND e.tenant_id=s.tenant_id
               AND e.branch_id=s.branch_id
               AND e.enrollment_status='active'
             INNER JOIN classes c
                ON c.id=e.class_id
               AND c.tenant_id=e.tenant_id
               AND c.branch_id=e.branch_id
             INNER JOIN sections sec
                ON sec.id=e.section_id
               AND sec.tenant_id=e.tenant_id
               AND sec.branch_id=e.branch_id
             INNER JOIN academic_years ay
                ON ay.id=e.academic_year_id
               AND ay.tenant_id=e.tenant_id
               AND ay.branch_id=e.branch_id
             WHERE s.tenant_id=:tenant_id
               AND s.branch_id=:branch_id
               AND s.status='active'
               AND s.deleted_at IS NULL
             ORDER BY c.display_order,c.class_name,
                      sec.section_name,student_name"
        );

        $query->execute([
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$scope['branch_id'],
        ]);

        $students=$query->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'academic_years'=>$years,
        'classes'=>$classes,
        'sections'=>$sections,
        'students'=>$students,
        'statuses'=>[
            'present',
            'absent',
            'half_day',
            'late',
            'leave',
            'on_duty',
            'holiday',
        ],
        'current_academic_year_id'=>(int)($years[0]['id']??0),
    ];
}


function attendanceLoadStudents(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    $academicYearId=(int)($filters['academic_year_id']??0);
    $classId=(int)($filters['class_id']??0);
    $sectionId=(int)($filters['section_id']??0);
    $attendanceDate=trim((string)($filters['attendance_date']??$filters['date']??''));

    if($academicYearId<=0){
        throw new InvalidArgumentException('Please select an academic year.');
    }

    if($classId<=0){
        throw new InvalidArgumentException('Please select a class.');
    }

    if($sectionId<=0){
        throw new InvalidArgumentException('Please select a section.');
    }

    $date=DateTimeImmutable::createFromFormat('Y-m-d',$attendanceDate);
    $dateErrors=DateTimeImmutable::getLastErrors();
    $hasDateErrors=is_array($dateErrors)
        &&(
            ($dateErrors['warning_count']??0)>0
            ||($dateErrors['error_count']??0)>0
        );

    if(
        !$date
        ||$hasDateErrors
        ||$date->format('Y-m-d')!==$attendanceDate
    ){
        throw new InvalidArgumentException('Please select a valid attendance date.');
    }

    $selectionCheck=$pdo->prepare(
        "SELECT COUNT(*)
         FROM classes c
         INNER JOIN academic_years ay
            ON ay.id=c.academic_year_id
           AND ay.tenant_id=c.tenant_id
           AND ay.branch_id=c.branch_id
         INNER JOIN sections sec
            ON sec.class_id=c.id
           AND sec.tenant_id=c.tenant_id
           AND sec.branch_id=c.branch_id
         WHERE c.id=:class_id
           AND sec.id=:section_id
           AND c.tenant_id=:tenant_id
           AND c.branch_id=:branch_id
           AND c.academic_year_id=:academic_year_id
           AND c.status='active'
           AND sec.status='active'"
    );

    $selectionCheck->execute([
        'class_id'=>$classId,
        'section_id'=>$sectionId,
        'tenant_id'=>$scope['tenant_id'],
        'branch_id'=>$scope['branch_id'],
        'academic_year_id'=>$academicYearId,
    ]);

    if((int)$selectionCheck->fetchColumn()===0){
        throw new InvalidArgumentException(
            'The selected class and section do not belong to the selected academic year.'
        );
    }

    $nameSql=studentNameSql('s');

    $query=$pdo->prepare(
        "SELECT
            s.id,
            s.admission_no AS admission_number,
            {$nameSql} AS student_name,
            e.roll_no,
            e.academic_year_id,
            e.class_id,
            c.class_name,
            e.section_id,
            sec.section_name,
            a.id AS attendance_id,
            a.status AS saved_status,
            a.remarks
         FROM student_enrollments e
         INNER JOIN students s
            ON s.id=e.student_id
           AND s.tenant_id=e.tenant_id
           AND s.branch_id=e.branch_id
         INNER JOIN classes c
            ON c.id=e.class_id
           AND c.tenant_id=e.tenant_id
           AND c.branch_id=e.branch_id
         INNER JOIN sections sec
            ON sec.id=e.section_id
           AND sec.tenant_id=e.tenant_id
           AND sec.branch_id=e.branch_id
         INNER JOIN academic_years ay
            ON ay.id=e.academic_year_id
           AND ay.tenant_id=e.tenant_id
           AND ay.branch_id=e.branch_id
         LEFT JOIN student_attendance a
            ON a.student_id=e.student_id
           AND a.tenant_id=e.tenant_id
           AND a.branch_id=e.branch_id
           AND a.academic_year_id=e.academic_year_id
           AND a.attendance_date=:attendance_date
         WHERE e.tenant_id=:tenant_id
           AND e.branch_id=:branch_id
           AND e.academic_year_id=:academic_year_id
           AND e.class_id=:class_id
           AND e.section_id=:section_id
           AND e.enrollment_status='active'
           AND s.status='active'
           AND s.deleted_at IS NULL
         ORDER BY
            CASE
                WHEN e.roll_no REGEXP '^[0-9]+$'
                THEN CAST(e.roll_no AS UNSIGNED)
                ELSE 999999999
            END,
            e.roll_no,
            s.first_name,
            s.last_name,
            s.id"
    );

    $query->execute([
        'attendance_date'=>$attendanceDate,
        'tenant_id'=>$scope['tenant_id'],
        'academic_year_id'=>$academicYearId,
        'class_id'=>$classId,
        'section_id'=>$sectionId,
        'branch_id'=>$scope['branch_id'],
    ]);

    $students=$query->fetchAll(PDO::FETCH_ASSOC);
    $counts=[
        'total'=>count($students),
        'present'=>0,
        'absent'=>0,
        'leave'=>0,
    ];
    $markedCount=0;

    foreach($students as &$student){
        $savedStatus=trim((string)($student['saved_status']??''));
        $student['attendance_exists']=(int)($student['attendance_id']??0)>0;

        if($student['attendance_exists']){
            $markedCount++;
        }

        $student['status']=$savedStatus!==''?$savedStatus:'present';
        $student['remarks']=(string)($student['remarks']??'');

        if($student['status']==='absent'){
            $counts['absent']++;
        }elseif($student['status']==='leave'){
            $counts['leave']++;
        }else{
            $counts['present']++;
        }

        unset($student['saved_status']);
    }
    unset($student);

    return [
        'students'=>$students,
        'attendance_exists'=>$markedCount>0,
        'marked_count'=>$markedCount,
        'is_complete'=>$counts['total']>0
            &&$markedCount===$counts['total'],
        'counts'=>$counts,
    ];
}

function attendanceRows(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    $where=[
        'a.tenant_id=:tenant_id',
        'a.branch_id=:branch_id',
        'a.attendance_date=:attendance_date',
    ];

    $params=[
        'tenant_id'=>$scope['tenant_id'],
        'branch_id'=>$scope['branch_id'],
        'attendance_date'=>trim(
            (string)($filters['date']??date('Y-m-d'))
        ),
    ];

    $search=trim((string)($filters['search']??''));

    if($search!==''){
        $where[]="(
            s.admission_no LIKE :search_term
            OR s.first_name LIKE :search_term
            OR s.last_name LIKE :search_term
            OR s.mobile LIKE :search_term
        )";
        $params['search_term']='%'.$search.'%';
    }

    foreach(
        ['academic_year_id','class_id','section_id','status']
        as $field
    ){
        $value=trim((string)($filters[$field]??''));

        if($value!==''&&$value!=='all'){
            if(in_array($field,['class_id','section_id'],true)){
                $where[]="e.{$field}=:{$field}";
            }else{
                $where[]="a.{$field}=:{$field}";
            }
            $params[$field]=$value;
        }
    }

    $nameSql=studentNameSql('s');

    $query=$pdo->prepare(
        "SELECT
            a.id,
            a.tenant_id,
            a.branch_id,
            a.academic_year_id,
            a.student_id,
            a.attendance_date,
            a.status,
            a.check_in AS check_in_time,
            a.check_out AS check_out_time,
            a.late_minutes,
            a.remarks,
            a.source,
            a.marked_by,
            a.created_at,
            s.admission_no AS admission_number,
            s.mobile,
            {$nameSql} AS student_name,
            e.class_id,
            c.class_name,
            e.section_id,
            sec.section_name,
            ay.year_name AS academic_year_name
         FROM student_attendance a
         INNER JOIN students s
            ON s.id=a.student_id
           AND s.tenant_id=a.tenant_id
           AND s.branch_id=a.branch_id
         LEFT JOIN student_enrollments e
            ON e.student_id=a.student_id
           AND e.academic_year_id=a.academic_year_id
           AND e.tenant_id=a.tenant_id
           AND e.branch_id=a.branch_id
         LEFT JOIN classes c
            ON c.id=e.class_id
           AND c.tenant_id=e.tenant_id
           AND c.branch_id=e.branch_id
         LEFT JOIN sections sec
            ON sec.id=e.section_id
           AND sec.tenant_id=e.tenant_id
           AND sec.branch_id=e.branch_id
         LEFT JOIN academic_years ay
            ON ay.id=a.academic_year_id
           AND ay.tenant_id=a.tenant_id
           AND ay.branch_id=a.branch_id
         WHERE ".implode(' AND ',$where)."
         ORDER BY
            COALESCE(c.display_order,9999),
            COALESCE(c.class_name,''),
            COALESCE(sec.section_name,''),
            s.first_name,
            s.last_name"
    );

    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function attendanceStats(
    PDO $pdo,
    array $scope,
    string $date
): array {
    $totalQuery=$pdo->prepare(
        "SELECT COUNT(DISTINCT s.id)
         FROM students s
         INNER JOIN student_enrollments e
            ON e.student_id=s.id
           AND e.tenant_id=s.tenant_id
           AND e.branch_id=s.branch_id
           AND e.enrollment_status='active'
         WHERE s.tenant_id=:tenant_id
           AND s.branch_id=:branch_id
           AND s.status='active'
           AND s.deleted_at IS NULL"
    );

    $totalQuery->execute([
        'tenant_id'=>$scope['tenant_id'],
        'branch_id'=>$scope['branch_id'],
    ]);

    $total=(int)$totalQuery->fetchColumn();

    $query=$pdo->prepare(
        "SELECT
            SUM(status='present') AS present,
            SUM(status='absent') AS absent,
            SUM(status='leave') AS leave_count,
            SUM(status='late') AS late_count,
            SUM(status='half_day') AS half_day_count,
            SUM(status='on_duty') AS on_duty_count,
            COUNT(*) AS marked
         FROM student_attendance
         WHERE tenant_id=:tenant_id
           AND branch_id=:branch_id
           AND attendance_date=:attendance_date"
    );

    $query->execute([
        'tenant_id'=>$scope['tenant_id'],
        'branch_id'=>$scope['branch_id'],
        'attendance_date'=>$date,
    ]);

    $row=$query->fetch(PDO::FETCH_ASSOC)?:[];

    $present=(int)($row['present']??0);
    $late=(int)($row['late_count']??0);
    $onDuty=(int)($row['on_duty_count']??0);
    $halfDay=(int)($row['half_day_count']??0);
    $marked=(int)($row['marked']??0);

    $effectivePresent=$present+$late+$onDuty+($halfDay*0.5);

    $row['total_students']=$total;
    $row['attendance_percentage']=$marked>0
        ?($effectivePresent/$marked)*100
        :0;

    return $row;
}

function weeklyData(PDO $pdo,array $scope): array
{
    $start=(new DateTimeImmutable('monday this week'))
        ->format('Y-m-d');

    $end=(new DateTimeImmutable('saturday this week'))
        ->format('Y-m-d');

    $query=$pdo->prepare(
        "SELECT
            e.class_id,
            c.class_name,
            e.section_id,
            sec.section_name,
            a.attendance_date,
            SUM(
                a.status IN('present','late','on_duty')
            ) AS full_present,
            SUM(a.status='half_day') AS half_day,
            COUNT(*) AS marked
         FROM student_attendance a
         INNER JOIN student_enrollments e
            ON e.student_id=a.student_id
           AND e.academic_year_id=a.academic_year_id
           AND e.tenant_id=a.tenant_id
           AND e.branch_id=a.branch_id
         INNER JOIN classes c
            ON c.id=e.class_id
           AND c.tenant_id=e.tenant_id
           AND c.branch_id=e.branch_id
         INNER JOIN sections sec
            ON sec.id=e.section_id
           AND sec.tenant_id=e.tenant_id
           AND sec.branch_id=e.branch_id
         WHERE a.tenant_id=:tenant_id
           AND a.branch_id=:branch_id
           AND a.attendance_date BETWEEN :start_date AND :end_date
         GROUP BY
            e.class_id,c.class_name,
            e.section_id,sec.section_name,
            a.attendance_date,c.display_order
         ORDER BY
            c.display_order,c.class_name,
            sec.section_name,a.attendance_date"
    );

    $query->execute([
        'tenant_id'=>$scope['tenant_id'],
        'branch_id'=>$scope['branch_id'],
        'start_date'=>$start,
        'end_date'=>$end,
    ]);

    $map=[];

    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
        $key=$row['class_id'].'-'.$row['section_id'];

        if(!isset($map[$key])){
            $map[$key]=[
                'class_name'=>$row['class_name'],
                'section_name'=>$row['section_name'],
                'mon'=>0,
                'tue'=>0,
                'wed'=>0,
                'thu'=>0,
                'fri'=>0,
                'sat'=>0,
            ];
        }

        $day=strtolower(
            (new DateTimeImmutable($row['attendance_date']))
                ->format('D')
        );

        $marked=(int)$row['marked'];
        $attended=(int)$row['full_present']
            +((int)$row['half_day']*0.5);

        if(isset($map[$key][$day])){
            $map[$key][$day]=$marked>0
                ?($attended/$marked)*100
                :0;
        }
    }

    return array_values($map);
}

if(!isset($pdo)||!$pdo instanceof PDO){
    attendanceJson(false,'Database connection unavailable.',[],500);
}

try{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
    ensureAttendanceTables($pdo);
}catch(Throwable $error){
    attendanceJson(
        false,
        'Unable to initialize attendance module: '
        .$error->getMessage(),
        [],
        500
    );
}

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}

if(
    empty($_SESSION['attendance_csrf_token'])
    ||!is_string($_SESSION['attendance_csrf_token'])
){
    $_SESSION['attendance_csrf_token']=bin2hex(random_bytes(32));
}

$scope=attendanceScope($pdo);

if($scope['tenant_id']<=0||$scope['branch_id']<=0){
    attendanceJson(
        false,
        'Active School and Branch context is required.',
        [],
        422
    );
}

$input=attendanceInput();

$action=strtolower(
    trim(
        (string)(
            $input['action']
            ??$_GET['action']
            ??''
        )
    )
);

try{
    if($action==='meta'){
        if(!attendanceCan('view')){
            throw new RuntimeException('Permission denied.',403);
        }

        attendanceJson(
            true,
            'Metadata loaded.',
            [
                'csrf_token'=>(string)
                    $_SESSION['attendance_csrf_token'],
                'meta'=>attendanceMeta($pdo,$scope),
                'permissions'=>[
                    'view'=>attendanceCan('view'),
                    'add'=>attendanceCan('add')
                        ||attendanceCan('create'),
                    'edit'=>attendanceCan('edit'),
                    'delete'=>attendanceCan('delete'),
                    'export'=>attendanceCan('export'),
                ],
            ]
        );
    }


    if($action==='load_students'){
        if(!attendanceCan('view')){
            throw new RuntimeException('Permission denied.',403);
        }

        attendanceJson(
            true,
            'Students loaded successfully.',
            attendanceLoadStudents(
                $pdo,
                $scope,
                $_GET+$input
            )
        );
    }

    if($action==='list'){
        if(!attendanceCan('view')){
            throw new RuntimeException('Permission denied.',403);
        }

        $filters=$_GET+$input;
        $date=trim(
            (string)(
                $filters['date']
                ??date('Y-m-d')
            )
        );

        attendanceJson(
            true,
            'Attendance loaded.',
            [
                'records'=>attendanceRows(
                    $pdo,
                    $scope,
                    $filters
                ),
                'stats'=>attendanceStats(
                    $pdo,
                    $scope,
                    $date
                ),
                'weekly'=>weeklyData(
                    $pdo,
                    $scope
                ),
            ]
        );
    }

    if($action==='export'){
        if(!attendanceCan('export')){
            throw new RuntimeException('Permission denied.',403);
        }

        $rows=attendanceRows($pdo,$scope,$_GET);

        while(ob_get_level()>0)ob_end_clean();

        header('Content-Type:text/csv; charset=utf-8');
        header(
            'Content-Disposition:attachment; '
            .'filename="student-attendance-'
            .date('Ymd-His').'.csv"'
        );

        $file=fopen('php://output','wb');

        fputcsv(
            $file,
            [
                'Date',
                'Admission No',
                'Student',
                'Class',
                'Section',
                'Status',
                'Check-in',
                'Check-out',
                'Remarks',
            ]
        );

        foreach($rows as $row){
            fputcsv(
                $file,
                [
                    $row['attendance_date'],
                    $row['admission_number'],
                    $row['student_name'],
                    $row['class_name'],
                    $row['section_name'],
                    $row['status'],
                    $row['check_in_time'],
                    $row['check_out_time'],
                    $row['remarks'],
                ]
            );
        }

        fclose($file);
        exit;
    }

    if($action==='save'){
        attendanceCsrf($input);

        $id=(int)($input['id']??0);

        if(
            !attendanceCan($id?'edit':'add')
            &&!attendanceCan($id?'edit':'create')
        ){
            throw new RuntimeException('Permission denied.',403);
        }

        foreach(
            [
                'attendance_date',
                'academic_year_id',
                'student_id',
                'status',
            ]
            as $field
        ){
            if(trim((string)($input[$field]??''))===''){
                throw new InvalidArgumentException(
                    'Complete all required attendance fields.'
                );
            }
        }

        $allowedStatuses=[
            'present','absent','half_day','late',
            'leave','on_duty','holiday',
        ];

        $status=trim((string)$input['status']);

        if(!in_array($status,$allowedStatuses,true)){
            throw new InvalidArgumentException(
                'Invalid attendance status.'
            );
        }

        $studentId=(int)$input['student_id'];
        $yearId=(int)$input['academic_year_id'];

        $studentCheck=$pdo->prepare(
            "SELECT COUNT(*)
             FROM students s
             INNER JOIN student_enrollments e
                ON e.student_id=s.id
               AND e.tenant_id=s.tenant_id
               AND e.branch_id=s.branch_id
             INNER JOIN academic_years ay
                ON ay.id=e.academic_year_id
               AND ay.tenant_id=e.tenant_id
               AND ay.branch_id=e.branch_id
             WHERE s.id=:student_id
               AND s.tenant_id=:tenant_id
               AND s.branch_id=:branch_id
               AND e.academic_year_id=:academic_year_id"
        );

        $studentCheck->execute([
            'student_id'=>$studentId,
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$scope['branch_id'],
            'academic_year_id'=>$yearId,
        ]);

        if((int)$studentCheck->fetchColumn()===0){
            throw new InvalidArgumentException(
                'Student enrollment was not found for the selected academic year.'
            );
        }

        $data=[
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$scope['branch_id'],
            'academic_year_id'=>$yearId,
            'student_id'=>$studentId,
            'attendance_date'=>trim(
                (string)$input['attendance_date']
            ),
            'status'=>$status,
            'check_in'=>trim(
                (string)($input['check_in_time']??'')
            )?:null,
            'check_out'=>trim(
                (string)($input['check_out_time']??'')
            )?:null,
            'late_minutes'=>max(
                0,
                (int)($input['late_minutes']??0)
            ),
            'remarks'=>mb_substr(
                trim((string)($input['remarks']??'')),
                0,
                255
            ),
            'source'=>in_array(
                (string)($input['source']??'manual'),
                ['manual','biometric','import','api'],
                true
            )
                ?(string)$input['source']
                :'manual',
            'marked_by'=>$scope['user_id'],
        ];

        if($data['branch_id']<=0){
            throw new InvalidArgumentException(
                'Branch session was not found.'
            );
        }

        if($data['marked_by']<=0){
            throw new InvalidArgumentException(
                'Logged-in user session was not found.'
            );
        }

        $old=null;

        if($id>0){
            $find=$pdo->prepare(
                "SELECT *
                 FROM student_attendance
                 WHERE id=:id
                   AND tenant_id=:tenant_id
                   AND branch_id=:branch_id"
            );
            $find->execute([
                'id'=>$id,
                'tenant_id'=>$scope['tenant_id'],
                'branch_id'=>$scope['branch_id'],
            ]);

            $old=$find->fetch(PDO::FETCH_ASSOC);

            if(!$old){
                throw new RuntimeException(
                    'Attendance record not found.',
                    404
                );
            }

            $query=$pdo->prepare(
                "UPDATE student_attendance SET
                    branch_id=:branch_id,
                    academic_year_id=:academic_year_id,
                    student_id=:student_id,
                    attendance_date=:attendance_date,
                    status=:status,
                    check_in=:check_in,
                    check_out=:check_out,
                    late_minutes=:late_minutes,
                    remarks=:remarks,
                    source=:source,
                    marked_by=:marked_by
                 WHERE id=:id
                   AND tenant_id=:tenant_id
                   AND branch_id=:branch_id"
            );

            $query->execute(
                $data+['id'=>$id]
            );
        }else{
            $query=$pdo->prepare(
                "INSERT INTO student_attendance(
                    tenant_id,branch_id,academic_year_id,
                    student_id,attendance_date,status,
                    check_in,check_out,late_minutes,
                    remarks,source,marked_by
                ) VALUES(
                    :tenant_id,:branch_id,:academic_year_id,
                    :student_id,:attendance_date,:status,
                    :check_in,:check_out,:late_minutes,
                    :remarks,:source,:marked_by
                )
                ON DUPLICATE KEY UPDATE
                    branch_id=VALUES(branch_id),
                    academic_year_id=VALUES(academic_year_id),
                    status=VALUES(status),
                    check_in=VALUES(check_in),
                    check_out=VALUES(check_out),
                    late_minutes=VALUES(late_minutes),
                    remarks=VALUES(remarks),
                    source=VALUES(source),
                    marked_by=VALUES(marked_by)"
            );

            $query->execute($data);
            $id=(int)$pdo->lastInsertId();

            if($id===0){
                $find=$pdo->prepare(
                    "SELECT id
                     FROM student_attendance
                     WHERE student_id=:student_id
                       AND tenant_id=:tenant_id
                       AND branch_id=:branch_id
                       AND attendance_date=:attendance_date
                     LIMIT 1"
                );
                $find->execute([
                    'student_id'=>$studentId,
                    'tenant_id'=>$scope['tenant_id'],
                    'branch_id'=>$scope['branch_id'],
                    'attendance_date'=>$data[
                        'attendance_date'
                    ],
                ]);
                $id=(int)$find->fetchColumn();
            }
        }

        logAttendance(
            $pdo,
            $scope,
            $old?'update':'save',
            $id,
            $studentId,
            $old,
            $data
        );

        attendanceJson(
            true,
            $old
                ?'Attendance updated successfully.'
                :'Attendance saved successfully.',
            ['id'=>$id]
        );
    }

    if($action==='bulk_save'){
        attendanceCsrf($input);

        if(
            !attendanceCan('edit')
            &&!attendanceCan('add')
            &&!attendanceCan('create')
        ){
            throw new RuntimeException(
                'Permission denied.',
                403
            );
        }

        $entries=$input['entries']??[];
        $yearId=(int)($input['academic_year_id']??0);
        $classId=(int)($input['class_id']??0);
        $sectionId=(int)($input['section_id']??0);
        $attendanceDate=trim(
            (string)($input['attendance_date']??'')
        );

        if(!is_array($entries)||$entries===[]){
            throw new InvalidArgumentException(
                'Load students before saving attendance.'
            );
        }

        if(
            $yearId<=0
            ||$classId<=0
            ||$sectionId<=0
            ||$attendanceDate===''
        ){
            throw new InvalidArgumentException(
                'Academic year, class, section and attendance date are required.'
            );
        }

        $date=DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $attendanceDate
        );
        $dateErrors=DateTimeImmutable::getLastErrors();
        $hasDateErrors=is_array($dateErrors)
            &&(
                ($dateErrors['warning_count']??0)>0
                ||($dateErrors['error_count']??0)>0
            );

        if(
            !$date
            ||$hasDateErrors
            ||$date->format('Y-m-d')!==$attendanceDate
        ){
            throw new InvalidArgumentException(
                'Please select a valid attendance date.'
            );
        }

        if($scope['branch_id']<=0||$scope['user_id']<=0){
            throw new InvalidArgumentException(
                'Branch or user session was not found.'
            );
        }

        $selectionCheck=$pdo->prepare(
            "SELECT COUNT(*)
             FROM classes c
             INNER JOIN academic_years ay
                ON ay.id=c.academic_year_id
               AND ay.tenant_id=c.tenant_id
               AND ay.branch_id=c.branch_id
             INNER JOIN sections sec
                ON sec.class_id=c.id
               AND sec.tenant_id=c.tenant_id
               AND sec.branch_id=c.branch_id
             WHERE c.id=:class_id
               AND sec.id=:section_id
               AND c.tenant_id=:tenant_id
               AND c.branch_id=:branch_id
               AND c.academic_year_id=:academic_year_id
               AND c.status='active'
               AND sec.status='active'"
        );

        $selectionCheck->execute([
            'class_id'=>$classId,
            'section_id'=>$sectionId,
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$scope['branch_id'],
            'academic_year_id'=>$yearId,
        ]);

        if((int)$selectionCheck->fetchColumn()===0){
            throw new InvalidArgumentException(
                'Please select a valid class and section for the selected academic year.'
            );
        }

        $existingQuery=$pdo->prepare(
            "SELECT
                a.student_id,
                a.id,
                a.status,
                a.remarks
             FROM student_attendance a
             INNER JOIN student_enrollments e
                ON e.student_id=a.student_id
               AND e.academic_year_id=a.academic_year_id
               AND e.tenant_id=a.tenant_id
               AND e.branch_id=a.branch_id
             WHERE a.tenant_id=:tenant_id
               AND a.branch_id=:branch_id
               AND a.academic_year_id=:academic_year_id
               AND a.attendance_date=:attendance_date
               AND e.class_id=:class_id
               AND e.section_id=:section_id"
        );

        $existingQuery->execute([
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$scope['branch_id'],
            'academic_year_id'=>$yearId,
            'attendance_date'=>$attendanceDate,
            'class_id'=>$classId,
            'section_id'=>$sectionId,
        ]);

        $existingByStudent=[];

        foreach(
            $existingQuery->fetchAll(PDO::FETCH_ASSOC)
            as $existing
        ){
            $existingByStudent[(int)$existing['student_id']]=$existing;
        }

        $studentCheck=$pdo->prepare(
            "SELECT COUNT(*)
             FROM students s
             INNER JOIN student_enrollments e
                ON e.student_id=s.id
               AND e.tenant_id=s.tenant_id
               AND e.branch_id=s.branch_id
             WHERE s.id=:student_id
               AND s.tenant_id=:tenant_id
               AND s.branch_id=:branch_id
               AND e.branch_id=:branch_id
               AND s.status='active'
               AND s.deleted_at IS NULL
               AND e.academic_year_id=:academic_year_id
               AND e.class_id=:class_id
               AND e.section_id=:section_id
               AND e.enrollment_status='active'"
        );

        $saveQuery=$pdo->prepare(
            "INSERT INTO student_attendance(
                tenant_id,branch_id,academic_year_id,
                student_id,attendance_date,status,
                check_in,check_out,late_minutes,
                remarks,source,marked_by
            ) VALUES(
                :tenant_id,:branch_id,:academic_year_id,
                :student_id,:attendance_date,:status,
                NULL,NULL,0,
                :remarks,'manual',:marked_by
            )
            ON DUPLICATE KEY UPDATE
                branch_id=VALUES(branch_id),
                academic_year_id=VALUES(academic_year_id),
                status=VALUES(status),
                check_in=NULL,
                check_out=NULL,
                late_minutes=0,
                remarks=VALUES(remarks),
                source='manual',
                marked_by=VALUES(marked_by)"
        );

        /*
         * Take Student Attendance supports every status displayed by the UI.
         * Students not changed by the user are submitted as Present.
         */
        $allowedStatuses=[
            'present',
            'absent',
            'half_day',
            'late',
            'leave',
            'on_duty',
            'holiday',
        ];
        $savedCount=0;
        $insertedCount=0;
        $updatedCount=0;

        $pdo->beginTransaction();

        foreach($entries as $entry){
            $studentId=(int)($entry['student_id']??0);

            if($studentId<=0){
                continue;
            }

            $studentCheck->execute([
                'student_id'=>$studentId,
                'tenant_id'=>$scope['tenant_id'],
                'branch_id'=>$scope['branch_id'],
                'academic_year_id'=>$yearId,
                'class_id'=>$classId,
                'section_id'=>$sectionId,
            ]);

            if((int)$studentCheck->fetchColumn()===0){
                continue;
            }

            $status=strtolower(
                trim((string)($entry['status']??'present'))
            );

            if(!in_array($status,$allowedStatuses,true)){
                $status='present';
            }

            $remarks=mb_substr(
                trim((string)($entry['remarks']??'')),
                0,
                255
            );

            $old=$existingByStudent[$studentId]??null;

            $saveQuery->execute([
                'tenant_id'=>$scope['tenant_id'],
                'branch_id'=>$scope['branch_id'],
                'academic_year_id'=>$yearId,
                'student_id'=>$studentId,
                'attendance_date'=>$attendanceDate,
                'status'=>$status,
                'remarks'=>$remarks!==''?$remarks:null,
                'marked_by'=>$scope['user_id'],
            ]);

            $attendanceId=(int)$pdo->lastInsertId();

            if($attendanceId<=0){
                $attendanceId=(int)($old['id']??0);
            }

            if($old){
                $updatedCount++;
            }else{
                $insertedCount++;
            }

            logAttendance(
                $pdo,
                $scope,
                $old?'update':'save',
                $attendanceId>0?$attendanceId:null,
                $studentId,
                $old,
                [
                    'academic_year_id'=>$yearId,
                    'class_id'=>$classId,
                    'section_id'=>$sectionId,
                    'attendance_date'=>$attendanceDate,
                    'status'=>$status,
                    'remarks'=>$remarks,
                ]
            );

            $savedCount++;
        }

        if($savedCount===0){
            throw new InvalidArgumentException(
                'No valid students were found for the selected class and section.'
            );
        }

        $pdo->commit();

        $wasUpdate=$updatedCount>0;

        attendanceJson(
            true,
            $wasUpdate
                ?'Attendance updated successfully.'
                :'Attendance saved successfully.',
            [
                'count'=>$savedCount,
                'inserted'=>$insertedCount,
                'updated'=>$updatedCount,
                'attendance_exists'=>true,
            ]
        );
    }


    attendanceJson(
        false,
        'Invalid attendance action.',
        [],
        400
    );
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();

    attendanceJson(
        false,
        $error->getMessage(),
        [],
        422
    );
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();

    $status=(int)$error->getCode();

    attendanceJson(
        false,
        $error->getMessage(),
        [],
        $status>=400&&$status<=599
            ?$status
            :403
    );
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();

    error_log(
        'attendance.php: '.$error->getMessage()
    );

    attendanceJson(
        false,
        'Attendance request failed: '
        .$error->getMessage(),
        [],
        500
    );
}

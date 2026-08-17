<?php
declare(strict_types=1);

define('SCHOOL_API_PAGE_KEY','subjects');

ob_start();
ini_set('display_errors','0');

require_once dirname(__DIR__).'/includes/bootstrap.php';

/* Build: 2026-08-15-subject-branch-context-v7 */

set_error_handler(
    static function(int $severity,string $message,string $file,int $line): bool {
        if(!(error_reporting()&$severity))return false;
        throw new ErrorException($message,0,$severity,$file,$line);
    }
);

function subjectsJson(
    bool $success,
    string $message='',
    array $data=[],
    int $status=200
): never {
    while(ob_get_level()>0)ob_end_clean();

    if(!headers_sent()){
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo json_encode(
        [
            'success'=>$success,
            'message'=>$message,
            'data'=>$data,
        ],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    );
    exit;
}

function subjectsInput(): array
{
    $contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));

    if(str_contains($contentType,'application/json')){
        $decoded=json_decode((string)file_get_contents('php://input'),true);
        return is_array($decoded)?$decoded:[];
    }

    return $_POST;
}

function subjectsCsrf(array $input): void
{
    $token=trim((string)($input['csrf_token']??''));

    $valid=function_exists('csrf_is_valid')
        ?csrf_is_valid($token)
        :(isset($_SESSION['csrf_token'])
            &&hash_equals((string)$_SESSION['csrf_token'],$token));

    if(!$valid){
        subjectsJson(false,'Invalid or expired CSRF token.',[],419);
    }
}

function subjectsTableExists(PDO $pdo,string $table): bool
{
    $s=$pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema=DATABASE()
           AND table_name=:table_name"
    );
    $s->execute(['table_name'=>$table]);
    return (int)$s->fetchColumn()>0;
}

function subjectsColumnExists(PDO $pdo,string $table,string $column): bool
{
    $s=$pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND column_name=:column_name"
    );
    $s->execute([
        'table_name'=>$table,
        'column_name'=>$column,
    ]);
    return (int)$s->fetchColumn()>0;
}

function subjectsIndexExists(PDO $pdo,string $table,string $index): bool
{
    $s=$pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND index_name=:index_name"
    );
    $s->execute([
        'table_name'=>$table,
        'index_name'=>$index,
    ]);
    return (int)$s->fetchColumn()>0;
}


function subjectsResolveSchoolScope(PDO $pdo): array
{
    if(session_status()!==PHP_SESSION_ACTIVE){
        session_start();
    }

    $user=function_exists('current_user')?current_user():[];
    $user=is_array($user)?$user:[];

    $tenantId=(int)(
        $user['tenant_id']
        ??$user['school_id']
        ??$_SESSION['tenant_id']
        ??$_SESSION['school_id']
        ??$_SESSION['tenant']['id']
        ??0
    );

    $branchId=(int)(
        $user['branch_id']
        ??$user['default_branch_id']
        ??$_SESSION['branch_id']
        ??$_SESSION['default_branch_id']
        ??0
    );

    $userId=(int)(
        $user['id']
        ??$user['user_id']
        ??$_SESSION['user_id']
        ??0
    );

    if($tenantId<=0){
        throw new RuntimeException(
            'School tenant session was not found. Sign out and sign in again.',
            401
        );
    }

    /*
     * Some older School sessions contain the School ID but not branch_id.
     * Resolve the logged user's default branch first.
     */
    if(
        $branchId<=0
        &&$userId>0
        &&subjectsTableExists($pdo,'users')
        &&subjectsColumnExists($pdo,'users','default_branch_id')
    ){
        $s=$pdo->prepare(
            "SELECT default_branch_id
             FROM users
             WHERE id=:user_id
               AND tenant_id=:tenant_id
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $s->execute([
            'user_id'=>$userId,
            'tenant_id'=>$tenantId,
        ]);
        $branchId=(int)$s->fetchColumn();
    }

    /*
     * School-level administrator accounts may have no explicit default branch.
     * In that case use the active main branch for the same school only.
     */
    if($branchId<=0&&subjectsTableExists($pdo,'branches')){
        $s=$pdo->prepare(
            "SELECT id
             FROM branches
             WHERE tenant_id=:tenant_id
               AND status='active'
             ORDER BY is_main DESC,id ASC
             LIMIT 1"
        );
        $s->execute(['tenant_id'=>$tenantId]);
        $branchId=(int)$s->fetchColumn();
    }

    if($branchId<=0){
        throw new RuntimeException(
            'Active School and Branch context is required. Select or assign an active branch to this School Admin.',
            422
        );
    }

    if(subjectsTableExists($pdo,'branches')){
        $s=$pdo->prepare(
            "SELECT COUNT(*)
             FROM branches
             WHERE id=:branch_id
               AND tenant_id=:tenant_id
               AND status='active'"
        );
        $s->execute([
            'branch_id'=>$branchId,
            'tenant_id'=>$tenantId,
        ]);

        if((int)$s->fetchColumn()<=0){
            throw new RuntimeException(
                'The active Branch does not belong to this School.',
                403
            );
        }
    }

    /*
     * Your DB triggers read these connection variables. Set them on this
     * exact PDO connection before any INSERT/UPDATE/DELETE.
     */
    $s=$pdo->prepare(
        "SET @schoolerp_tenant_id=:tenant_id,
             @schoolerp_branch_id=:branch_id"
    );
    $s->execute([
        'tenant_id'=>$tenantId,
        'branch_id'=>$branchId,
    ]);

    return [
        'tenant_id'=>$tenantId,
        'branch_id'=>$branchId,
        'user_id'=>$userId,
        'user'=>$user,
    ];
}

function subjectsLength(string $value): int
{
    return function_exists('mb_strlen')
        ?mb_strlen($value,'UTF-8')
        :strlen($value);
}

function subjectsEnsureSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS school_subjects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,
            academic_year_id BIGINT UNSIGNED NOT NULL,
            class_id BIGINT UNSIGNED NULL,
            class_name VARCHAR(150) NULL,
            subject_name VARCHAR(150) NOT NULL,
            subject_code VARCHAR(50) NOT NULL,
            book_name VARCHAR(200) NOT NULL DEFAULT '',
            subject_type ENUM('core','elective','language','practical','activity')
                NOT NULL DEFAULT 'core',
            department_name VARCHAR(100) NULL,
            subject_group VARCHAR(100) NULL,
            maximum_marks INT UNSIGNED NOT NULL DEFAULT 100,
            pass_marks INT UNSIGNED NOT NULL DEFAULT 35,
            subject_teacher_user_id BIGINT UNSIGNED NULL,
            subject_teacher_name VARCHAR(150) NULL,
            status ENUM('active','inactive','archived')
                NOT NULL DEFAULT 'active',
            description TEXT NULL,
            display_order INT NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_subject_class_scope
                (tenant_id,academic_year_id,class_id,status),
            KEY idx_subject_name_scope
                (tenant_id,academic_year_id,class_id,subject_name)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $columns=[
        'branch_id'=>"BIGINT UNSIGNED NULL AFTER tenant_id",
        'class_id'=>"BIGINT UNSIGNED NULL AFTER academic_year_id",
        'class_name'=>"VARCHAR(150) NULL AFTER class_id",
        'book_name'=>"VARCHAR(200) NOT NULL DEFAULT '' AFTER subject_code",
    ];

    foreach($columns as $column=>$definition){
        if(!subjectsColumnExists($pdo,'school_subjects',$column)){
            $pdo->exec(
                "ALTER TABLE school_subjects
                 ADD COLUMN {$column} {$definition}"
            );
        }
    }

    foreach(['uq_subject_year_name','uq_subject_year_code'] as $oldIndex){
        if(subjectsIndexExists($pdo,'school_subjects',$oldIndex)){
            $pdo->exec("ALTER TABLE school_subjects DROP INDEX {$oldIndex}");
        }
    }

    if(!subjectsIndexExists($pdo,'school_subjects','idx_subject_class_scope')){
        $pdo->exec(
            "ALTER TABLE school_subjects
             ADD INDEX idx_subject_class_scope
             (tenant_id,academic_year_id,class_id,status)"
        );
    }

    if(!subjectsIndexExists($pdo,'school_subjects','idx_subject_name_scope')){
        $pdo->exec(
            "ALTER TABLE school_subjects
             ADD INDEX idx_subject_name_scope
             (tenant_id,academic_year_id,class_id,subject_name)"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS subject_class_assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,
            class_id BIGINT UNSIGNED NOT NULL,
            class_name VARCHAR(150) NOT NULL,
            section_id BIGINT UNSIGNED NULL,
            section_name VARCHAR(100) NULL,
            academic_year_name VARCHAR(50) NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_subject_class_lookup
                (tenant_id,subject_id,status)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    if(
        subjectsTableExists($pdo,'subject_class_assignments')
        &&!subjectsColumnExists($pdo,'subject_class_assignments','branch_id')
    ){
        $pdo->exec(
            "ALTER TABLE subject_class_assignments
             ADD COLUMN branch_id BIGINT UNSIGNED NULL AFTER tenant_id"
        );
    }

    if(
        subjectsTableExists($pdo,'subject_class_assignments')
        &&subjectsColumnExists($pdo,'subject_class_assignments','class_id')
        &&subjectsColumnExists($pdo,'subject_class_assignments','class_name')
    ){
        $pdo->exec(
            "UPDATE school_subjects s
             JOIN (
                SELECT tenant_id,subject_id,MIN(id) assignment_id
                FROM subject_class_assignments
                WHERE class_id>0
                GROUP BY tenant_id,subject_id
             ) picked
                ON picked.tenant_id=s.tenant_id
               AND picked.subject_id=s.id
             JOIN subject_class_assignments sca
                ON sca.id=picked.assignment_id
             SET s.class_id=sca.class_id,
                 s.class_name=sca.class_name
             WHERE s.class_id IS NULL OR s.class_id=0"
        );
    }
}

function subjectsNormalName(string $value): string
{
    $value=trim(
        function_exists('mb_strtolower')
            ?mb_strtolower($value,'UTF-8')
            :strtolower($value)
    );
    return preg_replace('/\s+/u',' ',$value)??$value;
}

/**
 * Return one effective Class Management row per Academic Year + Class Name.
 * Subject Management is class-wise, not section-wise, so A/General/B rows
 * must not become duplicate class tabs. General is preferred when present.
 */
function subjectsEffectiveClasses(PDO $pdo,int $tenantId,int $branchId): array
{
    if(
        $tenantId<=0
        ||$branchId<=0
        ||!subjectsTableExists($pdo,'class_management_classes')
    ){
        return [];
    }

    $hasCode=subjectsColumnExists($pdo,'class_management_classes','class_code');
    $hasYear=subjectsColumnExists($pdo,'class_management_classes','academic_year_id');
    $hasStatus=subjectsColumnExists($pdo,'class_management_classes','status');
    $hasOrder=subjectsColumnExists($pdo,'class_management_classes','display_order');
    $hasSection=subjectsColumnExists($pdo,'class_management_classes','section_name');
    $hasBranch=subjectsColumnExists($pdo,'class_management_classes','branch_id');

    $codeSql=$hasCode?"COALESCE(class_code,'')":"''";
    $yearSql=$hasYear?'academic_year_id':'0';
    $statusSql=$hasStatus?"COALESCE(status,'active')":"'active'";
    $orderSql=$hasOrder?'display_order':'0';
    $sectionSql=$hasSection?"COALESCE(section_name,'')":"''";

    $sql=
        "SELECT id,class_name,
                {$codeSql} AS class_code,
                {$yearSql} AS academic_year_id,
                {$statusSql} AS status,
                {$orderSql} AS display_order,
                {$sectionSql} AS section_name
         FROM class_management_classes
         WHERE tenant_id=:tenant_id";

    $params=['tenant_id'=>$tenantId];

    if($hasBranch){
        $sql.=" AND branch_id=:branch_id";
        $params['branch_id']=$branchId;
    }

    if($hasStatus){
        $sql.=" AND COALESCE(status,'active')<>'archived'";
    }

    $sql.="
         ORDER BY {$yearSql},
                  CASE WHEN LOWER(TRIM({$sectionSql}))='general' THEN 0 ELSE 1 END,
                  {$orderSql},class_name,id";

    $s=$pdo->prepare($sql);
    $s->execute($params);
    $rows=$s->fetchAll(PDO::FETCH_ASSOC);

    $effective=[];
    foreach($rows as $row){
        $yearId=(int)($row['academic_year_id']??0);
        $name=trim((string)($row['class_name']??''));
        if($name==='')continue;

        $key=$yearId.'|'.subjectsNormalName($name);
        if(isset($effective[$key]))continue;

        $row['id']=(int)$row['id'];
        $row['academic_year_id']=$yearId;
        $row['display_order']=(int)($row['display_order']??0);
        $effective[$key]=$row;
    }

    return array_values($effective);
}

function subjectsEffectiveClassMaps(PDO $pdo,int $tenantId,int $branchId): array
{
    $byKey=[];
    $byId=[];

    foreach(subjectsEffectiveClasses($pdo,$tenantId,$branchId) as $row){
        $key=(int)$row['academic_year_id'].'|'.subjectsNormalName((string)$row['class_name']);
        $byKey[$key]=$row;
        $byId[(int)$row['id']]=$row;
    }

    return ['by_key'=>$byKey,'by_id'=>$byId];
}

final class SubjectsManagementModel
{
    public function __construct(private PDO $pdo) {}

    public function listSubjects(int $tenantId,int $branchId,array $filters=[]): array
    {
        $where=[
            's.tenant_id=:tenant_id',
            's.branch_id=:branch_id',
        ];
        $params=[
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
        ];

        $yearId=(int)($filters['academic_year_id']??0);
        $classId=(int)($filters['class_id']??0);

        if($yearId>0){
            $where[]='s.academic_year_id=:academic_year_id';
            $params['academic_year_id']=$yearId;
        }

        /*
         * Existing subject rows may contain an OLD class_management_classes.id
         * after a class was deleted/re-created. Resolve the selected current
         * class by Year + Class Name as well as by its current ID.
         */
        if($classId>0){
            $selected=$this->schoolClass($tenantId,$branchId,$classId,$yearId);
            if($selected){
                $where[]="(s.class_id=:class_id OR LOWER(TRIM(COALESCE(s.class_name,'')))=LOWER(TRIM(:filter_class_name)))";
                $params['class_id']=$classId;
                $params['filter_class_name']=(string)$selected['class_name'];
            }else{
                $where[]='s.class_id=:class_id';
                $params['class_id']=$classId;
            }
        }

        $status=strtolower(trim((string)($filters['status']??'')));
        if($status!==''&&$status!=='all'){
            $where[]='s.status=:status';
            $params['status']=$status;
        }

        $search=trim((string)($filters['search']??''));
        if($search!==''){
            $where[]=
                "(s.subject_name LIKE :search_subject
                  OR s.subject_code LIKE :search_code
                  OR s.book_name LIKE :search_book
                  OR s.subject_teacher_name LIKE :search_teacher
                  OR s.department_name LIKE :search_department)";

            $like='%'.$search.'%';
            $params['search_subject']=$like;
            $params['search_code']=$like;
            $params['search_book']=$like;
            $params['search_teacher']=$like;
            $params['search_department']=$like;
        }

        $sql=
            "SELECT s.*,ay.year_name AS academic_year_name
             FROM school_subjects s
             LEFT JOIN academic_years ay
                ON ay.id=s.academic_year_id
               AND ay.tenant_id=s.tenant_id
             WHERE ".implode(' AND ',$where)."
             ORDER BY s.academic_year_id,s.display_order,s.subject_name,s.id";

        $s=$this->pdo->prepare($sql);
        $s->execute($params);
        $rows=$s->fetchAll(PDO::FETCH_ASSOC);

        $maps=subjectsEffectiveClassMaps($this->pdo,$tenantId,$branchId);
        $byKey=$maps['by_key'];
        $byId=$maps['by_id'];

        foreach($rows as &$row){
            $storedId=(int)($row['class_id']??0);
            $row['stored_class_id']=$storedId;
            $subjectYearId=(int)($row['academic_year_id']??0);
            $storedName=trim((string)($row['class_name']??''));

            $resolved=null;

            /* Exact current ID first. */
            if($storedId>0&&isset($byId[$storedId])){
                $candidate=$byId[$storedId];
                if((int)$candidate['academic_year_id']===$subjectYearId){
                    $resolved=$candidate;
                }
            }

            /* Stale/missing ID: recover by the saved Year + Class Name. */
            if(!$resolved&&$storedName!==''){
                $key=$subjectYearId.'|'.subjectsNormalName($storedName);
                $resolved=$byKey[$key]??null;
            }

            if($resolved){
                $row['class_id']=(int)$resolved['id'];
                $row['class_name']=(string)$resolved['class_name'];
                $row['class_code']=(string)($resolved['class_code']??'');
                $row['class_resolution']=$storedId===(int)$resolved['id']
                    ?'current_id'
                    :'matched_by_year_and_name';
                $row['is_unassigned']=0;
            }else{
                /* Never hide legacy/orphan rows. Frontend shows Unassigned. */
                $row['class_id']=0;
                $row['class_code']='';
                $row['class_name']=$storedName!==''?$storedName:'Unassigned';
                $row['class_resolution']='unassigned_or_removed_class';
                $row['is_unassigned']=1;
            }
        }
        unset($row);

        return $rows;
    }

    public function findSubject(int $tenantId,int $branchId,int $id): ?array
    {
        $s=$this->pdo->prepare(
            "SELECT *
             FROM school_subjects
             WHERE id=:id
               AND tenant_id=:tenant_id
               AND branch_id=:branch_id
             LIMIT 1"
        );
        $s->execute([
            'id'=>$id,
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
        ]);
        return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function academicYear(int $tenantId,int $id): ?array
    {
        $s=$this->pdo->prepare(
            "SELECT id,year_name
             FROM academic_years
             WHERE tenant_id=:tenant_id
               AND id=:id
             LIMIT 1"
        );
        $s->execute([
            'tenant_id'=>$tenantId,
            'id'=>$id,
        ]);
        return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function schoolClass(int $tenantId,int $branchId,int $classId,int $yearId): ?array
    {
        if(!subjectsTableExists($this->pdo,'class_management_classes')){
            return null;
        }

        $hasYear=subjectsColumnExists(
            $this->pdo,
            'class_management_classes',
            'academic_year_id'
        );

        $hasCode=subjectsColumnExists(
            $this->pdo,
            'class_management_classes',
            'class_code'
        );

        $hasBranch=subjectsColumnExists(
            $this->pdo,
            'class_management_classes',
            'branch_id'
        );

        $codeSql=$hasCode?"COALESCE(class_code,'')":"''";
        $yearSql=$hasYear?'academic_year_id':'0';

        $sql=
            "SELECT id,class_name,
                    {$codeSql} AS class_code,
                    {$yearSql} AS academic_year_id
             FROM class_management_classes
             WHERE tenant_id=:tenant_id
               AND id=:id";

        $params=[
            'tenant_id'=>$tenantId,
            'id'=>$classId,
        ];

        if($hasBranch){
            $sql.=" AND branch_id=:branch_id";
            $params['branch_id']=$branchId;
        }

        if($hasYear&&$yearId>0){
            $sql.=" AND academic_year_id=:academic_year_id";
            $params['academic_year_id']=$yearId;
        }

        $sql.=" LIMIT 1";

        $s=$this->pdo->prepare($sql);
        $s->execute($params);
        return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    private function currentClassName(int $tenantId,int $branchId,int $yearId,int $classId): string
    {
        $class=$this->schoolClass($tenantId,$branchId,$classId,$yearId);
        return trim((string)($class['class_name']??''));
    }

    public function duplicateNameExists(
        int $tenantId,
        int $branchId,
        int $yearId,
        int $classId,
        string $name,
        int $excludeId=0
    ): bool {
        $className=$this->currentClassName($tenantId,$branchId,$yearId,$classId);

        $sql=
            "SELECT id
             FROM school_subjects
             WHERE tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND academic_year_id=:academic_year_id
               AND LOWER(TRIM(subject_name))=LOWER(TRIM(:subject_name))";

        $params=[
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
            'academic_year_id'=>$yearId,
            'subject_name'=>$name,
        ];

        if($className!==''){
            $sql.=" AND (class_id=:class_id OR LOWER(TRIM(COALESCE(class_name,'')))=LOWER(TRIM(:class_name)))";
            $params['class_id']=$classId;
            $params['class_name']=$className;
        }else{
            $sql.=' AND class_id=:class_id';
            $params['class_id']=$classId;
        }

        if($excludeId>0){
            $sql.=" AND id<>:exclude_id";
            $params['exclude_id']=$excludeId;
        }

        $s=$this->pdo->prepare($sql." LIMIT 1");
        $s->execute($params);
        return (bool)$s->fetchColumn();
    }

    public function duplicateCodeExists(
        int $tenantId,
        int $branchId,
        int $yearId,
        int $classId,
        string $code,
        int $excludeId=0
    ): bool {
        $className=$this->currentClassName($tenantId,$branchId,$yearId,$classId);

        $sql=
            "SELECT id
             FROM school_subjects
             WHERE tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND academic_year_id=:academic_year_id
               AND UPPER(TRIM(subject_code))=UPPER(TRIM(:subject_code))";

        $params=[
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
            'academic_year_id'=>$yearId,
            'subject_code'=>$code,
        ];

        if($className!==''){
            $sql.=" AND (class_id=:class_id OR LOWER(TRIM(COALESCE(class_name,'')))=LOWER(TRIM(:class_name)))";
            $params['class_id']=$classId;
            $params['class_name']=$className;
        }else{
            $sql.=' AND class_id=:class_id';
            $params['class_id']=$classId;
        }

        if($excludeId>0){
            $sql.=" AND id<>:exclude_id";
            $params['exclude_id']=$excludeId;
        }

        $s=$this->pdo->prepare($sql." LIMIT 1");
        $s->execute($params);
        return (bool)$s->fetchColumn();
    }

    public function save(int $tenantId,int $branchId,int $userId,int $id,array $data): int
    {
        $params=[
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
            'academic_year_id'=>$data['academic_year_id'],
            'class_id'=>$data['class_id'],
            'class_name'=>$data['class_name'],
            'subject_name'=>$data['subject_name'],
            'subject_code'=>$data['subject_code'],
            'book_name'=>$data['book_name'],
            'subject_type'=>$data['subject_type'],
            'department_name'=>$data['department_name'],
            'subject_group'=>$data['subject_group'],
            'maximum_marks'=>$data['maximum_marks'],
            'pass_marks'=>$data['pass_marks'],
            'subject_teacher_user_id'=>$data['subject_teacher_user_id']?:null,
            'subject_teacher_name'=>$data['subject_teacher_name'],
            'status'=>$data['status'],
            'description'=>$data['description'],
        ];

        if($id>0){
            $params['id']=$id;
            $params['updated_by']=$userId;

            $s=$this->pdo->prepare(
                "UPDATE school_subjects
                 SET academic_year_id=:academic_year_id,
                     class_id=:class_id,
                     class_name=:class_name,
                     subject_name=:subject_name,
                     subject_code=:subject_code,
                     book_name=:book_name,
                     subject_type=:subject_type,
                     department_name=:department_name,
                     subject_group=:subject_group,
                     maximum_marks=:maximum_marks,
                     pass_marks=:pass_marks,
                     subject_teacher_user_id=:subject_teacher_user_id,
                     subject_teacher_name=:subject_teacher_name,
                     status=:status,
                     description=:description,
                     updated_by=:updated_by,
                     updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id
                   AND tenant_id=:tenant_id
                   AND branch_id=:branch_id"
            );

            $s->execute($params);
            return $id;
        }

        $params['created_by']=$userId;

        $s=$this->pdo->prepare(
            "INSERT INTO school_subjects(
                tenant_id,
                branch_id,
                academic_year_id,
                class_id,
                class_name,
                subject_name,
                subject_code,
                book_name,
                subject_type,
                department_name,
                subject_group,
                maximum_marks,
                pass_marks,
                subject_teacher_user_id,
                subject_teacher_name,
                status,
                description,
                display_order,
                created_by
             ) VALUES(
                :tenant_id,
                :branch_id,
                :academic_year_id,
                :class_id,
                :class_name,
                :subject_name,
                :subject_code,
                :book_name,
                :subject_type,
                :department_name,
                :subject_group,
                :maximum_marks,
                :pass_marks,
                :subject_teacher_user_id,
                :subject_teacher_name,
                :status,
                :description,
                0,
                :created_by
             )"
        );

        $s->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function syncClassAssignment(
        int $tenantId,
        int $branchId,
        int $userId,
        int $subjectId,
        array $data
    ): void {
        if(!subjectsTableExists($this->pdo,'subject_class_assignments')){
            return;
        }

        $deleteSql=
            "DELETE FROM subject_class_assignments
             WHERE tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND subject_id=:subject_id";

        if(subjectsColumnExists(
            $this->pdo,
            'subject_class_assignments',
            'section_id'
        )){
            $deleteSql.=" AND section_id IS NULL";
        }

        $delete=$this->pdo->prepare($deleteSql);
        $delete->execute([
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
            'subject_id'=>$subjectId,
        ]);

        $columns=['tenant_id','branch_id','subject_id','class_id'];
        $values=[':tenant_id',':branch_id',':subject_id',':class_id'];
        $params=[
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
            'subject_id'=>$subjectId,
            'class_id'=>$data['class_id'],
        ];

        $optional=[
            'class_name'=>$data['class_name'],
            'academic_year_name'=>$data['academic_year_name'],
            'status'=>$data['status']==='active'?'active':'inactive',
            'created_by'=>$userId,
        ];

        foreach($optional as $column=>$value){
            if(!subjectsColumnExists(
                $this->pdo,
                'subject_class_assignments',
                $column
            )){
                continue;
            }

            $columns[]=$column;
            $values[]=':'.$column;
            $params[$column]=$value;
        }

        foreach(['section_id','section_name'] as $column){
            if(subjectsColumnExists(
                $this->pdo,
                'subject_class_assignments',
                $column
            )){
                $columns[]=$column;
                $values[]='NULL';
            }
        }

        $s=$this->pdo->prepare(
            "INSERT INTO subject_class_assignments("
            .implode(',',$columns).
            ") VALUES("
            .implode(',',$values).
            ")"
        );

        $s->execute($params);
    }

    public function delete(int $tenantId,int $branchId,int $id): void
    {
        foreach(
            [
                'subject_teacher_timetable_assignments',
                'subject_class_assignments',
            ] as $table
        ){
            if(!subjectsTableExists($this->pdo,$table)){
                continue;
            }

            $sql=
                "DELETE FROM {$table}
                 WHERE tenant_id=:tenant_id
                   AND subject_id=:subject_id";

            $params=[
                'tenant_id'=>$tenantId,
                'subject_id'=>$id,
            ];

            if(subjectsColumnExists($this->pdo,$table,'branch_id')){
                $sql.=" AND branch_id=:branch_id";
                $params['branch_id']=$branchId;
            }

            $s=$this->pdo->prepare($sql);
            $s->execute($params);
        }

        $s=$this->pdo->prepare(
            "DELETE FROM school_subjects
             WHERE id=:id
               AND tenant_id=:tenant_id
               AND branch_id=:branch_id"
        );
        $s->execute([
            'id'=>$id,
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
        ]);
    }

    public function meta(int $tenantId,int $branchId): array
    {
        $academicYears=[];

        if(subjectsTableExists($this->pdo,'academic_years')){
            try{
                $s=$this->pdo->prepare(
                    "SELECT id,year_name,is_current,status
                     FROM academic_years
                     WHERE tenant_id=:tenant_id
                     ORDER BY is_current DESC,start_date DESC,id DESC"
                );
                $s->execute(['tenant_id'=>$tenantId]);
                $academicYears=$s->fetchAll(PDO::FETCH_ASSOC);
            }catch(Throwable){
                $s=$this->pdo->prepare(
                    "SELECT id,year_name,0 AS is_current,'active' AS status
                     FROM academic_years
                     WHERE tenant_id=:tenant_id
                     ORDER BY id DESC"
                );
                $s->execute(['tenant_id'=>$tenantId]);
                $academicYears=$s->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        /* One class tab per class name/year; section rows are deduplicated. */
        $classes=subjectsEffectiveClasses($this->pdo,$tenantId,$branchId);

        return [
            'academic_years'=>$academicYears,
            'classes'=>$classes,
            'teachers'=>$this->teachers($tenantId,$branchId),
            'subject_types'=>[
                'core',
                'elective',
                'language',
                'practical',
                'activity',
            ],
        ];
    }

    private function teachers(int $tenantId,int $branchId): array
    {
        if(!subjectsTableExists($this->pdo,'users')){
            return [];
        }

        $nameParts=[];

        foreach(
            ['display_name','full_name','name','username','email']
            as $column
        ){
            if(subjectsColumnExists($this->pdo,'users',$column)){
                $nameParts[]="NULLIF({$column},'')";
            }
        }

        $nameExpression=$nameParts
            ?"COALESCE(".implode(',',$nameParts).",CONCAT('User #',id))"
            :"CONCAT('User #',id)";

        $where=[];
        $params=[];

        if(subjectsColumnExists($this->pdo,'users','tenant_id')){
            $where[]='tenant_id=:tenant_id';
            $params['tenant_id']=$tenantId;
        }

        if(subjectsColumnExists($this->pdo,'users','status')){
            $where[]="status='active'";
        }

        if(subjectsColumnExists($this->pdo,'users','default_branch_id')){
            $where[]='default_branch_id=:branch_id';
            $params['branch_id']=$branchId;
        }

        $sql=
            "SELECT id,{$nameExpression} AS teacher_name
             FROM users"
            .($where?' WHERE '.implode(' AND ',$where):'')
            ." ORDER BY teacher_name";

        $s=$this->pdo->prepare($sql);
        $s->execute($params);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}

final class SubjectsManagementController
{
    private SubjectsManagementModel $model;
    private ?array $requestScope=null;

    public function __construct(private PDO $pdo,private array $user)
    {
        $this->model=new SubjectsManagementModel($pdo);
    }

    private function requestScope(): array
    {
        if($this->requestScope===null){
            $this->requestScope=subjectsResolveSchoolScope($this->pdo);
        }

        return $this->requestScope;
    }

    public function tenantId(): int
    {
        return (int)($this->requestScope()['tenant_id']??0);
    }

    public function branchId(): int
    {
        return (int)($this->requestScope()['branch_id']??0);
    }

    public function userId(): int
    {
        return (int)($this->requestScope()['user_id']??0);
    }

    private function platformFullAccess(): bool
    {
        if(function_exists('is_super_admin')){
            try{
                if((bool)is_super_admin())return true;
            }catch(Throwable){
            }
        }

        $roleText=strtolower(trim((string)(
            $this->user['role_key']
            ??$this->user['role_name']
            ??$this->user['role']
            ??$this->user['user_type']
            ??$_SESSION['role_key']
            ??$_SESSION['role_name']
            ??$_SESSION['role']
            ??''
        )));

        $roleKey=preg_replace('/[^a-z0-9]+/','_',$roleText)??'';

        return in_array(
            trim($roleKey,'_'),
            [
                'platform_owner',
                'platformowner',
                'platform_admin',
                'platformadministrator',
                'super_admin',
                'superadministrator',
            ],
            true
        );
    }

    public function can(string $action): bool
    {
        if($this->platformFullAccess()){
            return true;
        }

        $action=strtolower(trim($action));
        if($action==='add')$action='create';

        if(
            function_exists('school_effective_permission')
            &&school_effective_permission('subjects',$action)
        ){
            return true;
        }

        if(!function_exists('has_permission')){
            return true;
        }

        $aliases=[$action];
        if($action==='create')$aliases[]='add';

        foreach(
            ['subjects','subject_management','academic_management']
            as $moduleKey
        ){
            foreach($aliases as $permissionAction){
                if(has_permission($moduleKey,$permissionAction)){
                    return true;
                }
            }

            if(has_permission($moduleKey,'full_access')){
                return true;
            }
        }

        return false;
    }

    private function requireTenant(): int
    {
        $tenantId=$this->tenantId();

        if($tenantId<=0){
            throw new RuntimeException(
                'School tenant session was not found. Sign out and sign in again.',
                401
            );
        }

        return $tenantId;
    }

    public function meta(): array
    {
        if(!$this->can('view')){
            throw new RuntimeException(
                'You do not have permission to view subjects.',
                403
            );
        }

        return $this->model->meta($this->requireTenant(),$this->branchId());
    }

    public function list(array $filters): array
    {
        if(!$this->can('view')){
            throw new RuntimeException(
                'You do not have permission to view subjects.',
                403
            );
        }

        return $this->model->listSubjects(
            $this->requireTenant(),
            $this->branchId(),
            $filters
        );
    }

    public function save(array $input): array
    {
        $id=(int)($input['id']??0);
        $requiredAction=$id>0?'edit':'create';

        if(!$this->can($requiredAction)){
            throw new RuntimeException(
                $id>0
                    ?'You do not have permission to edit subjects.'
                    :'You do not have permission to create subjects.',
                403
            );
        }

        $tenantId=$this->requireTenant();
        $branchId=$this->branchId();
        $data=$this->validate($tenantId,$branchId,$input);

        if($this->model->duplicateNameExists(
            $tenantId,
            $branchId,
            $data['academic_year_id'],
            $data['class_id'],
            $data['subject_name'],
            $id
        )){
            throw new InvalidArgumentException(
                'This subject name already exists in the selected Class and Academic Year.'
            );
        }

        if($this->model->duplicateCodeExists(
            $tenantId,
            $branchId,
            $data['academic_year_id'],
            $data['class_id'],
            $data['subject_code'],
            $id
        )){
            throw new InvalidArgumentException(
                'This subject code already exists in the selected Class and Academic Year.'
            );
        }

        $this->pdo->beginTransaction();

        try{
            $savedId=$this->model->save(
                $tenantId,
                $branchId,
                $this->userId(),
                $id,
                $data
            );

            $this->model->syncClassAssignment(
                $tenantId,
                $branchId,
                $this->userId(),
                $savedId,
                $data
            );

            $this->pdo->commit();
            return ['id'=>$savedId];
        }catch(Throwable $exception){
            if($this->pdo->inTransaction()){
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function importRows(array $rows): array
    {
        if(!$this->can('import')){
            throw new RuntimeException('You do not have permission to import subjects.',403);
        }

        $tenantId=$this->requireTenant();
        $branchId=$this->branchId();

        if(count($rows)>2000){
            throw new InvalidArgumentException('A maximum of 2,000 subject rows can be imported at one time.');
        }

        $meta=$this->model->meta($tenantId,$branchId);

        $yearMap=[];
        foreach(($meta['academic_years']??[]) as $year){
            $name=strtolower(trim((string)($year['year_name']??'')));
            if($name!=='')$yearMap[$name]=(int)$year['id'];
        }

        $classMap=[];
        foreach(($meta['classes']??[]) as $class){
            $className=strtolower(trim((string)($class['class_name']??'')));
            if($className==='')continue;

            $classYearId=(int)($class['academic_year_id']??0);
            $classMap[$classYearId.'|'.$className]=(int)$class['id'];

            if($classYearId<=0){
                $classMap['0|'.$className]=(int)$class['id'];
            }
        }

        $imported=0;
        $skipped=0;
        $failed=0;
        $errors=[];

        foreach($rows as $index=>$row){
            $rowNumber=$index+2;

            try{
                $yearName=trim((string)($row['academic_year']??''));
                $className=trim((string)($row['class']??''));
                $subjectName=trim((string)($row['subject_name']??''));
                $subjectCode=strtoupper(trim((string)($row['subject_code']??'')));

                if($yearName===''||$className===''||$subjectName===''||$subjectCode===''){
                    throw new InvalidArgumentException('Required value missing.');
                }

                $yearId=$yearMap[strtolower($yearName)]??0;
                if($yearId<=0){
                    throw new InvalidArgumentException('Academic Year "'.$yearName.'" was not found.');
                }

                $normalizedClass=strtolower($className);
                $classId=
                    $classMap[$yearId.'|'.$normalizedClass]
                    ??$classMap['0|'.$normalizedClass]
                    ??0;

                if($classId<=0){
                    throw new InvalidArgumentException('Class "'.$className.'" was not found in '.$yearName.'.');
                }

                $payload=[
                    'academic_year_id'=>$yearId,
                    'class_id'=>$classId,
                    'subject_name'=>$subjectName,
                    'subject_code'=>$subjectCode,
                    'book_name'=>trim((string)($row['book_name']??'')),
                    'subject_type'=>strtolower(trim((string)($row['subject_type']??'core'))),
                    'department_name'=>trim((string)($row['department']??'')),
                    'subject_group'=>trim((string)($row['subject_group']??'')),
                    'maximum_marks'=>(int)($row['maximum_marks']??100),
                    'pass_marks'=>(int)($row['pass_marks']??35),
                    'subject_teacher_user_id'=>0,
                    'subject_teacher_name'=>trim((string)($row['subject_teacher']??'')),
                    'status'=>strtolower(trim((string)($row['status']??'active'))),
                    'description'=>trim((string)($row['description']??'')),
                ];

                $data=$this->validate($tenantId,$branchId,$payload);

                if(
                    $this->model->duplicateNameExists(
                        $tenantId,$branchId,$data['academic_year_id'],$data['class_id'],$data['subject_name']
                    )
                    ||$this->model->duplicateCodeExists(
                        $tenantId,$branchId,$data['academic_year_id'],$data['class_id'],$data['subject_code']
                    )
                ){
                    $skipped++;
                    if(count($errors)<50){
                        $errors[]='Row '.$rowNumber.': duplicate Subject Name or Subject Code for this Class + Academic Year; skipped.';
                    }
                    continue;
                }

                $this->pdo->beginTransaction();

                try{
                    $savedId=$this->model->save($tenantId,$branchId,$this->userId(),0,$data);
                    $this->model->syncClassAssignment($tenantId,$branchId,$this->userId(),$savedId,$data);
                    $this->pdo->commit();
                    $imported++;
                }catch(Throwable $exception){
                    if($this->pdo->inTransaction())$this->pdo->rollBack();
                    throw $exception;
                }
            }catch(Throwable $exception){
                if($this->pdo->inTransaction())$this->pdo->rollBack();
                $failed++;
                if(count($errors)<50){
                    $errors[]='Row '.$rowNumber.': '.$exception->getMessage();
                }
            }
        }

        return [
            'imported'=>$imported,
            'skipped'=>$skipped,
            'failed'=>$failed,
            'errors'=>$errors,
        ];
    }

    public function delete(int $id): void
    {
        if(!$this->can('delete')){
            throw new RuntimeException(
                'You do not have permission to delete subjects.',
                403
            );
        }

        $tenantId=$this->requireTenant();
        $branchId=$this->branchId();

        if(!$this->model->findSubject($tenantId,$branchId,$id)){
            throw new RuntimeException('Subject not found.',404);
        }

        $this->pdo->beginTransaction();

        try{
            $this->model->delete($tenantId,$branchId,$id);
            $this->pdo->commit();
        }catch(Throwable $exception){
            if($this->pdo->inTransaction()){
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function validate(int $tenantId,int $branchId,array $input): array
    {
        $yearId=(int)($input['academic_year_id']??0);
        $classId=(int)($input['class_id']??0);
        $name=trim((string)($input['subject_name']??''));
        $code=strtoupper(trim((string)($input['subject_code']??'')));
        $bookName=trim((string)($input['book_name']??''));
        $maximum=(int)($input['maximum_marks']??0);
        $pass=(int)($input['pass_marks']??0);

        if($name===''||subjectsLength($name)>150){
            throw new InvalidArgumentException('Enter a valid subject name.');
        }

        if(!preg_match('/^[A-Z0-9._\/ -]{1,50}$/',$code)){
            throw new InvalidArgumentException(
                'Subject code may contain letters, numbers, spaces, dot, slash, underscore and hyphen.'
            );
        }

        if($yearId<=0){
            throw new InvalidArgumentException('Select an Academic Year.');
        }

        $year=$this->model->academicYear($tenantId,$yearId);

        if(!$year){
            throw new InvalidArgumentException(
                'Selected Academic Year was not found for this school.'
            );
        }

        if($classId<=0){
            throw new InvalidArgumentException('Select a Class.');
        }

        $class=$this->model->schoolClass($tenantId,$branchId,$classId,$yearId);

        if(!$class){
            throw new InvalidArgumentException(
                'Selected Class does not belong to the selected Academic Year.'
            );
        }

        if(subjectsLength($bookName)>200){
            throw new InvalidArgumentException(
                'Book Name must not exceed 200 characters.'
            );
        }

        if($maximum<=0||$maximum>1000){
            throw new InvalidArgumentException(
                'Maximum marks must be between 1 and 1000.'
            );
        }

        if($pass<0||$pass>$maximum){
            throw new InvalidArgumentException(
                'Pass marks cannot exceed maximum marks.'
            );
        }

        $status=strtolower(trim((string)($input['status']??'active')));

        if(!in_array($status,['active','inactive','archived'],true)){
            $status='active';
        }

        $type=strtolower(trim((string)($input['subject_type']??'core')));

        if(!in_array($type,['core','elective','language','practical','activity'],true)){
            $type='core';
        }

        return [
            'academic_year_id'=>$yearId,
            'academic_year_name'=>(string)$year['year_name'],
            'class_id'=>$classId,
            'class_name'=>(string)$class['class_name'],
            'subject_name'=>$name,
            'subject_code'=>$code,
            'book_name'=>$bookName,
            'subject_type'=>$type,
            'department_name'=>trim((string)($input['department_name']??'')),
            'subject_group'=>trim((string)($input['subject_group']??'')),
            'maximum_marks'=>$maximum,
            'pass_marks'=>$pass,
            'subject_teacher_user_id'=>(int)($input['subject_teacher_user_id']??0),
            'subject_teacher_name'=>trim((string)($input['subject_teacher_name']??'')),
            'status'=>$status,
            'description'=>trim((string)($input['description']??'')),
        ];
    }
}


function subjectsNormalizeHeader(string $value): string
{
    $value=strtolower(trim($value));
    $value=preg_replace('/[^a-z0-9]+/','_',$value)??'';
    $value=trim($value,'_');

    $aliases=[
        'academic_year'=>'academic_year',
        'academic_year_name'=>'academic_year',
        'year'=>'academic_year',
        'class'=>'class',
        'class_name'=>'class',
        'subject'=>'subject_name',
        'subject_name'=>'subject_name',
        'book'=>'book_name',
        'book_name'=>'book_name',
        'subject_code'=>'subject_code',
        'code'=>'subject_code',
        'status'=>'status',
        'subject_type'=>'subject_type',
        'type'=>'subject_type',
        'department'=>'department',
        'department_name'=>'department',
        'subject_group'=>'subject_group',
        'group'=>'subject_group',
        'maximum_marks'=>'maximum_marks',
        'max_marks'=>'maximum_marks',
        'pass_marks'=>'pass_marks',
        'subject_teacher'=>'subject_teacher',
        'teacher'=>'subject_teacher',
        'description'=>'description',
        'remarks'=>'description',
    ];

    return $aliases[$value]??$value;
}

function subjectsRowsFromMatrix(array $matrix): array
{
    if(!$matrix){
        throw new InvalidArgumentException('The import file is empty.');
    }

    $rawHeaders=array_shift($matrix);
    $headers=[];

    foreach($rawHeaders as $header){
        $headers[]=subjectsNormalizeHeader((string)$header);
    }

    foreach(['academic_year','class','subject_name','subject_code'] as $required){
        if(!in_array($required,$headers,true)){
            throw new InvalidArgumentException(
                'Missing required column: '.str_replace('_',' ',$required).'.'
            );
        }
    }

    $rows=[];

    foreach($matrix as $rawRow){
        $hasValue=false;
        foreach($rawRow as $cell){
            if(trim((string)$cell)!==''){
                $hasValue=true;
                break;
            }
        }
        if(!$hasValue)continue;

        $row=[];
        foreach($headers as $index=>$header){
            if($header==='')continue;
            $row[$header]=trim((string)($rawRow[$index]??''));
        }
        $rows[]=$row;
    }

    if(!$rows){
        throw new InvalidArgumentException('No subject data rows were found in the import file.');
    }

    return $rows;
}

function subjectsReadCsvFile(string $path): array
{
    $handle=fopen($path,'rb');
    if(!$handle){
        throw new RuntimeException('Unable to open the CSV import file.',500);
    }

    $matrix=[];
    $rowNumber=0;

    while(($row=fgetcsv($handle,0,',','"','\\'))!==false){
        $rowNumber++;
        if($rowNumber===1&&isset($row[0])){
            $row[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$row[0])??(string)$row[0];
        }
        $matrix[]=$row;
    }

    fclose($handle);
    return subjectsRowsFromMatrix($matrix);
}

function subjectsXlsxColumnIndex(string $reference): int
{
    if(!preg_match('/^([A-Z]+)/i',$reference,$match))return 0;

    $index=0;
    foreach(str_split(strtoupper($match[1])) as $letter){
        $index=$index*26+(ord($letter)-64);
    }
    return max(0,$index-1);
}

function subjectsReadXlsxFile(string $path): array
{
    if(!class_exists('ZipArchive')){
        throw new RuntimeException('XLSX import requires the PHP ZIP extension (ZipArchive).',500);
    }

    $zip=new ZipArchive();
    if($zip->open($path)!==true){
        throw new InvalidArgumentException('The XLSX file could not be opened.');
    }

    try{
        $shared=[];
        $sharedXml=$zip->getFromName('xl/sharedStrings.xml');

        if($sharedXml!==false){
            $xml=simplexml_load_string($sharedXml);
            if($xml!==false){
                foreach($xml->si as $item){
                    $value='';
                    if(isset($item->t)){
                        $value=(string)$item->t;
                    }elseif(isset($item->r)){
                        foreach($item->r as $run)$value.=(string)$run->t;
                    }
                    $shared[]=$value;
                }
            }
        }

        $sheetXml=$zip->getFromName('xl/worksheets/sheet1.xml');
        if($sheetXml===false){
            throw new InvalidArgumentException('The XLSX file does not contain worksheet 1.');
        }

        $xml=simplexml_load_string($sheetXml);
        if($xml===false){
            throw new InvalidArgumentException('The XLSX worksheet is invalid.');
        }

        $matrix=[];

        foreach($xml->sheetData->row as $row){
            $values=[];
            $maxIndex=-1;

            foreach($row->c as $cell){
                $columnIndex=subjectsXlsxColumnIndex((string)$cell['r']);
                $type=(string)$cell['t'];
                $value='';

                if($type==='inlineStr'){
                    if(isset($cell->is->t)){
                        $value=(string)$cell->is->t;
                    }elseif(isset($cell->is->r)){
                        foreach($cell->is->r as $run)$value.=(string)$run->t;
                    }
                }else{
                    $raw=isset($cell->v)?(string)$cell->v:'';
                    $value=$type==='s'?($shared[(int)$raw]??''):$raw;
                }

                $values[$columnIndex]=$value;
                $maxIndex=max($maxIndex,$columnIndex);
            }

            $normalized=[];
            for($i=0;$i<=$maxIndex;$i++)$normalized[]=$values[$i]??'';
            $matrix[]=$normalized;
        }

        return subjectsRowsFromMatrix($matrix);
    }finally{
        $zip->close();
    }
}

function subjectsParseImportFile(array $file): array
{
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);

    if($error!==UPLOAD_ERR_OK){
        throw new InvalidArgumentException(
            match($error){
                UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'The import file is too large.',
                UPLOAD_ERR_NO_FILE=>'Choose a CSV or XLSX file.',
                default=>'The import file upload failed.',
            }
        );
    }

    $name=(string)($file['name']??'');
    $tmp=(string)($file['tmp_name']??'');

    if($tmp===''||!is_uploaded_file($tmp)){
        throw new InvalidArgumentException('The uploaded import file is invalid.');
    }

    $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));

    if(!in_array($extension,['csv','xlsx'],true)){
        throw new InvalidArgumentException('Only CSV and XLSX files are supported.');
    }

    if((int)($file['size']??0)>10*1024*1024){
        throw new InvalidArgumentException('The import file must be 10 MB or smaller.');
    }

    return $extension==='csv'
        ?subjectsReadCsvFile($tmp)
        :subjectsReadXlsxFile($tmp);
}

function subjectsXmlEscape(string $value): string
{
    return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');
}

function subjectsXlsxColumnName(int $index): string
{
    $name='';
    $index++;
    while($index>0){
        $index--;
        $name=chr(65+($index%26)).$name;
        $index=intdiv($index,26);
    }
    return $name;
}

function subjectsDownloadTemplate(
    SubjectsManagementController $controller,
    string $format
): never {
    if(!$controller->can('import')){
        throw new RuntimeException(
            'You do not have permission to download the Subject import template.',
            403
        );
    }

    $meta=$controller->meta();
    $years=$meta['academic_years']??[];
    $classes=$meta['classes']??[];

    $yearName=(string)($years[0]['year_name']??'2026-2027');
    $yearId=(int)($years[0]['id']??0);

    foreach($years as $year){
        if((int)($year['is_current']??0)===1){
            $yearName=(string)$year['year_name'];
            $yearId=(int)$year['id'];
            break;
        }
    }

    $className='Class 1';
    foreach($classes as $class){
        $classYearId=(int)($class['academic_year_id']??0);
        if($yearId<=0||$classYearId<=0||$classYearId===$yearId){
            $className=(string)($class['class_name']??'Class 1');
            break;
        }
    }

    $headers=[
        'Academic Year','Class','Subject Name','Book Name','Subject Code','Status',
        'Subject Type','Department','Subject Group','Maximum Marks','Pass Marks',
        'Subject Teacher','Description',
    ];

    $sample=[
        $yearName,$className,'Mathematics','Mathematics Textbook','MAT-01','active',
        'core','Mathematics','Core','100','35','',
        'Sample subject row - replace with your data.',
    ];

    while(ob_get_level()>0)ob_end_clean();

    if($format==='csv'){
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subject-import-template.csv"');
        $out=fopen('php://output','wb');
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,$headers,',','"','\\');
        fputcsv($out,$sample,',','"','\\');
        fclose($out);
        exit;
    }

    if($format!=='xlsx'){
        throw new InvalidArgumentException('Template format must be csv or xlsx.');
    }

    if(!class_exists('ZipArchive')){
        throw new RuntimeException('XLSX template download requires the PHP ZIP extension (ZipArchive).',500);
    }

    $tmp=tempnam(sys_get_temp_dir(),'subject_template_');
    if($tmp===false){
        throw new RuntimeException('Unable to create the XLSX template.',500);
    }

    $zip=new ZipArchive();
    if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){
        @unlink($tmp);
        throw new RuntimeException('Unable to build the XLSX template.',500);
    }

    $contentTypes='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'</Types>';

    $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';

    $workbook='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Subjects Import" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'</Relationships>';

    $row1='';
    foreach($headers as $i=>$value){
        $ref=subjectsXlsxColumnName($i).'1';
        $row1.='<c r="'.$ref.'" t="inlineStr"><is><t>'.subjectsXmlEscape($value).'</t></is></c>';
    }

    $row2='';
    foreach($sample as $i=>$value){
        $ref=subjectsXlsxColumnName($i).'2';
        $row2.='<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'
            .subjectsXmlEscape((string)$value)
            .'</t></is></c>';
    }

    $last=subjectsXlsxColumnName(count($headers)-1);

    $sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<dimension ref="A1:'.$last.'2"/>'
        .'<sheetData><row r="1">'.$row1.'</row><row r="2">'.$row2.'</row></sheetData>'
        .'<autoFilter ref="A1:'.$last.'2"/>'
        .'</worksheet>';

    $zip->addFromString('[Content_Types].xml',$contentTypes);
    $zip->addFromString('_rels/.rels',$rels);
    $zip->addFromString('xl/workbook.xml',$workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels',$workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="subject-import-template.xlsx"');
    header('Content-Length: '.filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}


function subjectsExport(
    SubjectsManagementController $controller,
    string $format
): never {
    $requiredPermission=match($format){
        'print'=>'print',
        'pdf'=>'pdf',
        default=>'export',
    };

    if(!$controller->can($requiredPermission)){
        throw new RuntimeException(
            'You do not have permission to '.$requiredPermission.' subjects.',
            403
        );
    }

    $subjects=$controller->list($_GET);

    $headers=[
        'Class',
        'Subject',
        'Code',
        'Book Name',
        'Academic Year',
        'Type',
        'Teacher',
        'Maximum Marks',
        'Pass Marks',
        'Status',
    ];

    $rows=array_map(
        static fn(array $subject): array=>[
            $subject['class_name']??'',
            $subject['subject_name']??'',
            $subject['subject_code']??'',
            $subject['book_name']??'',
            $subject['academic_year_name']??'',
            $subject['subject_type']??'',
            $subject['subject_teacher_name']??'',
            $subject['maximum_marks']??'',
            $subject['pass_marks']??'',
            $subject['status']??'',
        ],
        $subjects
    );

    while(ob_get_level()>0)ob_end_clean();

    if($format==='excel'){
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header(
            'Content-Disposition: attachment; filename="classwise-subjects-'
            .date('Ymd-His')
            .'.xls"'
        );
        echo "\xEF\xBB\xBF";
    }else{
        header('Content-Type: text/html; charset=utf-8');
    }

    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<title>Class-Wise Subjects</title>';
    echo '<style>';
    echo 'body{font-family:Arial,sans-serif;padding:20px;color:#111827}';
    echo 'h1{font-size:20px;margin:0 0 14px}';
    echo 'table{border-collapse:collapse;width:100%;font-size:11px}';
    echo 'th,td{border:1px solid #cbd5e1;padding:6px;text-align:left}';
    echo 'th{background:#f1f5f9}';
    echo '</style></head>';
    echo '<body'.($format==='print'?' onload="window.print()"':'').'>';
    echo '<h1>Class-Wise Subjects Report</h1><table><thead><tr>';

    foreach($headers as $header){
        echo '<th>'.htmlspecialchars($header,ENT_QUOTES,'UTF-8').'</th>';
    }

    echo '</tr></thead><tbody>';

    foreach($rows as $row){
        echo '<tr>';
        foreach($row as $value){
            echo '<td>'.htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8').'</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
    exit;
}

if(!isset($pdo)||!$pdo instanceof PDO){
    subjectsJson(false,'Database connection unavailable.',[],500);
}

try{
    $requestScope=subjectsResolveSchoolScope($pdo);
    subjectsEnsureSchema($pdo);
}catch(Throwable $exception){
    error_log('subjects-schema: '.$exception->getMessage());

    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $local=str_contains($host,'localhost')||str_contains($host,'127.0.0.1');

    subjectsJson(
        false,
        $local
            ?'Subjects database setup failed: '.$exception->getMessage()
            :'Unable to prepare Subjects database tables.',
        [
            'build'=>'2026-08-15-subject-branch-context-v7',
        ],
        500
    );
}

$resolvedUser=function_exists('current_user')?current_user():[];
$user=is_array($resolvedUser)?$resolvedUser:[];
$controller=new SubjectsManagementController($pdo,$user);
$input=subjectsInput();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    if($action==='template'){
        subjectsDownloadTemplate(
            $controller,
            strtolower(trim((string)($_GET['format']??'xlsx')))
        );
    }

    if($action==='export'){
        subjectsExport(
            $controller,
            strtolower(trim((string)($_GET['format']??'print')))
        );
    }

    if($action==='meta'){
        $canCreate=$controller->can('create');

        subjectsJson(
            true,
            'Subject metadata loaded.',
            [
                'csrf_token'=>function_exists('csrfToken')?csrfToken():'',
                'meta'=>$controller->meta(),
                'permissions'=>[
                    'view'=>$controller->can('view'),
                    'create'=>$canCreate,
                    'add'=>$canCreate,
                    'edit'=>$controller->can('edit'),
                    'delete'=>$controller->can('delete'),
                    'print'=>$controller->can('print'),
                    'pdf'=>$controller->can('pdf'),
                    'export'=>$controller->can('export'),
                    'import'=>$controller->can('import'),
                ],
                'build'=>'2026-08-15-subject-branch-context-v7',
            ]
        );
    }

    if($action==='list'){
        subjectsJson(
            true,
            'Subjects loaded.',
            [
                'subjects'=>$controller->list($_GET+$input),
            ]
        );
    }

    subjectsCsrf($input);

    if($action==='import'){
        if(!$controller->can('import')){
            throw new RuntimeException('You do not have permission to import subjects.',403);
        }

        $rows=subjectsParseImportFile($_FILES['import_file']??[]);
        $result=$controller->importRows($rows);

        subjectsJson(true,'Subject import completed.',$result);
    }

    if($action==='save'){
        $result=$controller->save($input);

        subjectsJson(
            true,
            (int)($input['id']??0)>0
                ?'Subject updated successfully.'
                :'Subject added successfully.',
            $result
        );
    }

    if($action==='delete'){
        $controller->delete((int)($input['id']??0));
        subjectsJson(true,'Subject deleted successfully.');
    }

    subjectsJson(false,'Invalid Subjects API action.',[],400);
}catch(InvalidArgumentException $exception){
    subjectsJson(false,$exception->getMessage(),[],422);
}catch(RuntimeException $exception){
    $status=$exception->getCode();

    subjectsJson(
        false,
        $exception->getMessage(),
        [],
        ($status>=400&&$status<=599)?$status:403
    );
}catch(Throwable $exception){
    error_log('subjects-api: '.$exception->getMessage());

    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $local=str_contains($host,'localhost')||str_contains($host,'127.0.0.1');

    subjectsJson(
        false,
        $local
            ?'Subject request failed: '.$exception->getMessage()
            :'Unable to complete the subject request.',
        [
            'build'=>'2026-08-15-subject-branch-context-v7',
        ],
        500
    );
}

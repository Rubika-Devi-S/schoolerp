<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');

$originalScriptName=(string)($_SERVER['SCRIPT_NAME']??'');
$_SERVER['SCRIPT_NAME']='/login.php';
require_once dirname(__DIR__).'/includes/bootstrap.php';
$_SERVER['SCRIPT_NAME']=$originalScriptName;

/* Build: 2026-08-10-subjects-classwise-reference-layout-v4 */

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

final class SubjectsManagementModel
{
    public function __construct(private PDO $pdo) {}

    public function listSubjects(int $tenantId,array $filters=[]): array
    {
        $where=['s.tenant_id=:tenant_id'];
        $params=['tenant_id'=>$tenantId];

        $yearId=(int)($filters['academic_year_id']??0);
        $classId=(int)($filters['class_id']??0);

        if($yearId>0){
            $where[]='s.academic_year_id=:academic_year_id';
            $params['academic_year_id']=$yearId;
        }

        if($classId>0){
            $where[]='s.class_id=:class_id';
            $params['class_id']=$classId;
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

        $hasClassCode=subjectsColumnExists(
            $this->pdo,
            'class_management_classes',
            'class_code'
        );

        $classCodeSql=$hasClassCode
            ?"COALESCE(cmc.class_code,'')"
            :"''";

        $sql=
            "SELECT
                s.*,
                ay.year_name AS academic_year_name,
                COALESCE(NULLIF(s.class_name,''),cmc.class_name)
                    AS resolved_class_name,
                {$classCodeSql} AS class_code
             FROM school_subjects s
             LEFT JOIN academic_years ay
                ON ay.id=s.academic_year_id
               AND ay.tenant_id=s.tenant_id
             LEFT JOIN class_management_classes cmc
                ON cmc.id=s.class_id
               AND cmc.tenant_id=s.tenant_id
             WHERE ".implode(' AND ',$where)."
             ORDER BY
                cmc.class_name,
                s.display_order,
                s.subject_name,
                s.id";

        $s=$this->pdo->prepare($sql);
        $s->execute($params);
        $rows=$s->fetchAll(PDO::FETCH_ASSOC);

        foreach($rows as &$row){
            $row['class_name']=
                $row['resolved_class_name']
                ??$row['class_name']
                ??'';
        }
        unset($row);

        return $rows;
    }

    public function findSubject(int $tenantId,int $id): ?array
    {
        $s=$this->pdo->prepare(
            "SELECT *
             FROM school_subjects
             WHERE id=:id
               AND tenant_id=:tenant_id
             LIMIT 1"
        );
        $s->execute([
            'id'=>$id,
            'tenant_id'=>$tenantId,
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

    public function schoolClass(int $tenantId,int $classId,int $yearId): ?array
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

        if($hasYear&&$yearId>0){
            $sql.=" AND academic_year_id=:academic_year_id";
            $params['academic_year_id']=$yearId;
        }

        $sql.=" LIMIT 1";

        $s=$this->pdo->prepare($sql);
        $s->execute($params);
        return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function duplicateNameExists(
        int $tenantId,
        int $yearId,
        int $classId,
        string $name,
        int $excludeId=0
    ): bool {
        $sql=
            "SELECT id
             FROM school_subjects
             WHERE tenant_id=:tenant_id
               AND academic_year_id=:academic_year_id
               AND class_id=:class_id
               AND LOWER(TRIM(subject_name))=LOWER(TRIM(:subject_name))";

        $params=[
            'tenant_id'=>$tenantId,
            'academic_year_id'=>$yearId,
            'class_id'=>$classId,
            'subject_name'=>$name,
        ];

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
        int $yearId,
        int $classId,
        string $code,
        int $excludeId=0
    ): bool {
        $sql=
            "SELECT id
             FROM school_subjects
             WHERE tenant_id=:tenant_id
               AND academic_year_id=:academic_year_id
               AND class_id=:class_id
               AND UPPER(TRIM(subject_code))=UPPER(TRIM(:subject_code))";

        $params=[
            'tenant_id'=>$tenantId,
            'academic_year_id'=>$yearId,
            'class_id'=>$classId,
            'subject_code'=>$code,
        ];

        if($excludeId>0){
            $sql.=" AND id<>:exclude_id";
            $params['exclude_id']=$excludeId;
        }

        $s=$this->pdo->prepare($sql." LIMIT 1");
        $s->execute($params);
        return (bool)$s->fetchColumn();
    }

    public function save(int $tenantId,int $userId,int $id,array $data): int
    {
        $params=[
            'tenant_id'=>$tenantId,
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
                   AND tenant_id=:tenant_id"
            );

            $s->execute($params);
            return $id;
        }

        $params['created_by']=$userId;

        $s=$this->pdo->prepare(
            "INSERT INTO school_subjects(
                tenant_id,
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
            'subject_id'=>$subjectId,
        ]);

        $columns=['tenant_id','subject_id','class_id'];
        $values=[':tenant_id',':subject_id',':class_id'];
        $params=[
            'tenant_id'=>$tenantId,
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

    public function delete(int $tenantId,int $id): void
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

            $s=$this->pdo->prepare(
                "DELETE FROM {$table}
                 WHERE tenant_id=:tenant_id
                   AND subject_id=:subject_id"
            );
            $s->execute([
                'tenant_id'=>$tenantId,
                'subject_id'=>$id,
            ]);
        }

        $s=$this->pdo->prepare(
            "DELETE FROM school_subjects
             WHERE id=:id
               AND tenant_id=:tenant_id"
        );
        $s->execute([
            'id'=>$id,
            'tenant_id'=>$tenantId,
        ]);
    }

    public function meta(int $tenantId): array
    {
        $academicYears=[];
        $classes=[];

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

        if(subjectsTableExists($this->pdo,'class_management_classes')){
            $hasCode=subjectsColumnExists(
                $this->pdo,
                'class_management_classes',
                'class_code'
            );

            $hasYear=subjectsColumnExists(
                $this->pdo,
                'class_management_classes',
                'academic_year_id'
            );

            $hasStatus=subjectsColumnExists(
                $this->pdo,
                'class_management_classes',
                'status'
            );

            $hasDisplayOrder=subjectsColumnExists(
                $this->pdo,
                'class_management_classes',
                'display_order'
            );

            $codeSql=$hasCode?"COALESCE(class_code,'')":"''";
            $yearSql=$hasYear?'academic_year_id':'0';
            $statusWhere=$hasStatus
                ?"AND COALESCE(status,'active')<>'archived'"
                :'';
            $orderSql=$hasDisplayOrder?'display_order,':'';

            $s=$this->pdo->prepare(
                "SELECT id,class_name,
                        {$codeSql} AS class_code,
                        {$yearSql} AS academic_year_id
                 FROM class_management_classes
                 WHERE tenant_id=:tenant_id
                   {$statusWhere}
                 ORDER BY {$orderSql} class_name,id"
            );

            $s->execute(['tenant_id'=>$tenantId]);
            $classes=$s->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'academic_years'=>$academicYears,
            'classes'=>$classes,
            'teachers'=>$this->teachers($tenantId),
            'subject_types'=>[
                'core',
                'elective',
                'language',
                'practical',
                'activity',
            ],
        ];
    }

    private function teachers(int $tenantId): array
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

    public function __construct(private PDO $pdo,private array $user)
    {
        $this->model=new SubjectsManagementModel($pdo);
    }

    public function tenantId(): int
    {
        return (int)(
            $this->user['tenant_id']
            ??$this->user['school_id']
            ??$_SESSION['tenant_id']
            ??$_SESSION['school_id']
            ??$_SESSION['tenant']['id']
            ??0
        );
    }

    public function userId(): int
    {
        return (int)(
            $this->user['id']
            ??$this->user['user_id']
            ??$_SESSION['user_id']
            ??0
        );
    }

    public function can(string $action): bool
    {
        if(function_exists('is_super_admin')&&is_super_admin()){
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

        return $this->model->meta($this->requireTenant());
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
        $data=$this->validate($tenantId,$input);

        if($this->model->duplicateNameExists(
            $tenantId,
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
                $this->userId(),
                $id,
                $data
            );

            $this->model->syncClassAssignment(
                $tenantId,
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

    public function delete(int $id): void
    {
        if(!$this->can('delete')){
            throw new RuntimeException(
                'You do not have permission to delete subjects.',
                403
            );
        }

        $tenantId=$this->requireTenant();

        if(!$this->model->findSubject($tenantId,$id)){
            throw new RuntimeException('Subject not found.',404);
        }

        $this->pdo->beginTransaction();

        try{
            $this->model->delete($tenantId,$id);
            $this->pdo->commit();
        }catch(Throwable $exception){
            if($this->pdo->inTransaction()){
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function validate(int $tenantId,array $input): array
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

        $class=$this->model->schoolClass($tenantId,$classId,$yearId);

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
            'build'=>'2026-08-10-subjects-classwise-reference-layout-v4',
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
                ],
                'build'=>'2026-08-10-subjects-classwise-reference-layout-v4',
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
            'build'=>'2026-08-10-subjects-classwise-reference-layout-v4',
        ],
        500
    );
}

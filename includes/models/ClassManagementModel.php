<?php
declare(strict_types=1);

final class ClassManagementModel
{
    public function __construct(private PDO $pdo) {}

    public function meta(int $tenantId): array
    {
        $years = $this->pdo->prepare("SELECT id, year_name, is_current, status FROM academic_years WHERE tenant_id=:tenant ORDER BY is_current DESC, start_date DESC");
        $years->execute(['tenant'=>$tenantId]);
        return ['academic_years'=>$years->fetchAll(PDO::FETCH_ASSOC), 'mediums'=>['English','Tamil','Hindi','Kannada','Other'], 'shifts'=>['Morning','General','Evening']];
    }

    public function list(int $tenantId, int $userId, string $access, array $f): array
    {
        $where=['c.tenant_id=:tenant']; $p=['tenant'=>$tenantId];
        if (in_array($access,['teacher','class_teacher'],true)) {
            $where[]="(c.class_teacher_user_id=:u1 OR EXISTS(SELECT 1 FROM class_teacher_allocations a WHERE a.class_id=c.id AND a.teacher_user_id=:u2 AND a.status='active'))";
            $p['u1']=$userId; $p['u2']=$userId;
        }
        if (($s=trim((string)($f['search']??'')))!=='') {
            $where[]="(c.class_name LIKE :s1 OR c.class_code LIKE :s2 OR c.section_name LIKE :s3 OR c.class_teacher_name LIKE :s4)";
            foreach(['s1','s2','s3','s4'] as $k) $p[$k]='%'.$s.'%';
        }
        foreach(['academic_year_id','medium','shift_name','status'] as $k) {
            $v=trim((string)($f[$k]??'')); if($v!==''&&$v!=='all'){ $where[]="c.$k=:$k"; $p[$k]=$v; }
        }
        $sql="SELECT c.*, ay.year_name academic_year_name FROM class_management_classes c LEFT JOIN academic_years ay ON ay.id=c.academic_year_id AND ay.tenant_id=c.tenant_id WHERE ".implode(' AND ',$where)." ORDER BY ay.start_date DESC,c.display_order,c.class_name,c.section_name";
        $st=$this->pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId,int $id): ?array
    {
        $st=$this->pdo->prepare("SELECT * FROM class_management_classes WHERE id=:id AND tenant_id=:tenant LIMIT 1");
        $st->execute(['id'=>$id,'tenant'=>$tenantId]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
    }

    public function duplicate(int $tenantId,int $year,string $name,string $section,int $exclude=0): bool
    {
        $sql="SELECT id FROM class_management_classes WHERE tenant_id=:t AND academic_year_id=:y AND LOWER(TRIM(class_name))=LOWER(TRIM(:n)) AND LOWER(TRIM(section_name))=LOWER(TRIM(:s))";
        $p=['t'=>$tenantId,'y'=>$year,'n'=>$name,'s'=>$section]; if($exclude>0){$sql.=' AND id<>:e';$p['e']=$exclude;} $sql.=' LIMIT 1';
        $st=$this->pdo->prepare($sql);$st->execute($p);return(bool)$st->fetchColumn();
    }

    public function save(int $tenantId,int $userId,array $d): int
    {
        $id=(int)($d['id']??0);
        $params=['tenant_id'=>$tenantId,'academic_year_id'=>$d['academic_year_id'],'class_name'=>$d['class_name'],'class_code'=>$d['class_code'],'section_name'=>$d['section_name'],'medium'=>$d['medium'],'shift_name'=>$d['shift_name'],'class_teacher_user_id'=>$d['class_teacher_user_id']?:null,'class_teacher_name'=>$d['class_teacher_name'],'classroom_name'=>$d['classroom_name'],'maximum_strength'=>$d['maximum_strength'],'current_strength'=>$d['current_strength'],'status'=>$d['status'],'description'=>$d['description'],'display_order'=>$d['display_order']];
        if($id>0){
            $params['id']=$id;$params['updated_by']=$userId;
            $set=[];foreach(array_keys($params) as $k){if(!in_array($k,['id','tenant_id'],true))$set[]="$k=:$k";}
            $st=$this->pdo->prepare("UPDATE class_management_classes SET ".implode(',',$set).",updated_at=CURRENT_TIMESTAMP WHERE id=:id AND tenant_id=:tenant_id");$st->execute($params);return$id;
        }
        $params['created_by']=$userId;$cols=array_keys($params);$st=$this->pdo->prepare("INSERT INTO class_management_classes (".implode(',',$cols).") VALUES (:".implode(',:',$cols).")");$st->execute($params);return(int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId,int $id): void
    { $st=$this->pdo->prepare("DELETE FROM class_management_classes WHERE id=:id AND tenant_id=:t");$st->execute(['id'=>$id,'t'=>$tenantId]); }

    public function related(int $tenantId,string $type,int $classId): array
    {
        $map=['subjects'=>'class_subject_allocations','teachers'=>'class_teacher_allocations','timetable'=>'class_timetable_entries','transfers'=>'class_student_transfers','promotions'=>'class_student_promotions'];
        if(!isset($map[$type]))return[];$st=$this->pdo->prepare("SELECT * FROM {$map[$type]} WHERE tenant_id=:t AND class_id=:c ORDER BY id DESC");$st->execute(['t'=>$tenantId,'c'=>$classId]);return$st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveRelated(int $tenantId,int $userId,string $type,array $d): int
    {
        $defs=[
            'subjects'=>['class_subject_allocations',['subject_name','subject_code','teacher_user_id','teacher_name','periods_per_week','status']],
            'teachers'=>['class_teacher_allocations',['teacher_user_id','teacher_name','assignment_type','subject_name','status']],
            'timetable'=>['class_timetable_entries',['day_name','period_no','start_time','end_time','subject_name','teacher_name','classroom_name','status']],
            'transfers'=>['class_student_transfers',['student_id','student_name','from_class_name','to_class_name','transfer_date','reason','status']],
            'promotions'=>['class_student_promotions',['student_id','student_name','from_class_name','to_class_name','promotion_date','result_status','remarks','status']],
        ];
        if(!isset($defs[$type]))throw new InvalidArgumentException('Invalid class operation.');[$table,$fields]=$defs[$type];$p=['tenant_id'=>$tenantId,'class_id'=>(int)$d['class_id'],'created_by'=>$userId];foreach($fields as $f)$p[$f]=($d[$f]??'')===''?null:$d[$f];$cols=array_keys($p);$st=$this->pdo->prepare("INSERT INTO $table (".implode(',',$cols).") VALUES (:".implode(',:',$cols).")");$st->execute($p);return(int)$this->pdo->lastInsertId();
    }

    public function deleteRelated(int $tenantId,string $type,int $id): void
    {
        $map=['subjects'=>'class_subject_allocations','teachers'=>'class_teacher_allocations','timetable'=>'class_timetable_entries','transfers'=>'class_student_transfers','promotions'=>'class_student_promotions'];if(!isset($map[$type]))throw new InvalidArgumentException('Invalid class operation.');$st=$this->pdo->prepare("DELETE FROM {$map[$type]} WHERE id=:id AND tenant_id=:t");$st->execute(['id'=>$id,'t'=>$tenantId]);
    }
}

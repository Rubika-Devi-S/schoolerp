<?php
declare(strict_types=1);

/* Parent Sidebar Permission API - Build 2026-08-13-permission-chain-parent-v23 */

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';

function psp_json(bool $success,string $message='',array $data=[],int $status=200): never {
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function psp_input(): array {
    $type=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
    if(str_contains($type,'application/json')){
        $d=json_decode((string)file_get_contents('php://input'),true);
        return is_array($d)?$d:[];
    }
    return $_POST;
}
function psp_scope(): array {
    $u=function_exists('current_user')?current_user():[];
    return [
        'tenant_id'=>(int)($u['tenant_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'user_id'=>(int)($u['id']??$_SESSION['user_id']??0),
        'role_id'=>(int)($u['role_id']??$_SESSION['role_id']??0),
        'role_key'=>pc_role_key((string)($u['role_key']??$_SESSION['role_key']??'')),
    ];
}
function psp_require_admin(PDO $pdo,array $scope): void {
    $r=pc_role($pdo,(int)$scope['tenant_id'],(int)$scope['role_id']);
    if(pc_role_key((string)($r['role_key']??$scope['role_key']))!=='school_admin'){
        psp_json(false,'Only School Administrator can manage Parent Sidebar Permission.',[],403);
    }
}
function psp_csrf(array $input): void {
    $t=(string)($input['csrf_token']??'');
    $ok=function_exists('csrf_is_valid')?csrf_is_valid($t):
        (isset($_SESSION['csrf_token'])&&hash_equals((string)$_SESSION['csrf_token'],$t));
    if(!$ok)psp_json(false,'Invalid or expired CSRF token.',[],419);
}
function psp_data(PDO $pdo,array $scope): array {
    $tenant=(int)$scope['tenant_id'];
    $parentRole=pc_role_id($pdo,$tenant,'parent');
    if($parentRole<=0)throw new RuntimeException('Parent role is not available for this school.');
    school_sidebar_service_ensure_role_master($pdo,'parent',(int)$scope['user_id']?:null);

    $actions=pc_action_catalog($pdo);
    $q=$pdo->prepare(
        "SELECT si.id AS sidebar_item_id,si.parent_id,si.menu_key,si.menu_title,
                si.route,si.icon,COALESCE(rm.display_order,si.display_order) AS display_order
         FROM sidebar_role_master_items rm
         INNER JOIN sidebar_items si ON si.id=rm.sidebar_item_id
         WHERE rm.role_key='parent' AND rm.is_enabled=1
           AND si.is_active=1 AND si.show_in_sidebar=1
           AND si.portal_scope IN('school','all')
         ORDER BY COALESCE(rm.display_order,si.display_order),si.id"
    );
    $q->execute();
    $delegate=$pdo->prepare(
        "SELECT action_key,is_allowed FROM school_sidebar_permission_grants
         WHERE tenant_id=:tenant AND role_id=:role AND sidebar_item_id=:item
           AND action_key LIKE 'parent_delegate\\_%'"
    );
    $items=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        $id=(int)$row['sidebar_item_id'];$caps=[];
        foreach($actions as $a){
            $key=(string)$a['action_key'];
            $caps[$key]=pc_super_cap($pdo,$tenant,$id,$key,'parent')?1:0;
        }
        if(empty($caps['view']))continue; // Super Admin did not allow this Parent option.
        $delegate->execute(['tenant'=>$tenant,'role'=>$parentRole,'item'=>$id]);
        $saved=[];
        foreach($delegate->fetchAll(PDO::FETCH_ASSOC) as $g){
            $key=preg_replace('/^parent_delegate_/','',(string)$g['action_key']);
            $saved[$key]=(int)$g['is_allowed'];
        }
        $perms=[];
        foreach($actions as $a){
            $key=(string)$a['action_key'];
            $perms[$key]=empty($caps[$key])?0:(array_key_exists($key,$saved)?(int)$saved[$key]:1);
        }
        $row['caps']=$caps;$row['permissions']=$perms;$row['is_visible']=(int)($perms['view']??0);
        $items[]=$row;
    }
    return ['parent_role_id'=>$parentRole,'actions'=>$actions,'items'=>$items];
}

if(!isset($pdo)||!($pdo instanceof PDO))psp_json(false,'Database unavailable.',[],500);
$scope=psp_scope();
if($scope['tenant_id']<=0)psp_json(false,'School session not found.',[],401);
psp_require_admin($pdo,$scope);
$input=psp_input();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'load')));

try{
    if($action==='load'){
        $data=psp_data($pdo,$scope);
        $data['csrf_token']=function_exists('csrfToken')?csrfToken():'';
        psp_json(true,'Parent Sidebar Permission loaded.',$data);
    }

    if($action==='save'){
        psp_csrf($input);
        $current=psp_data($pdo,$scope);
        $roleId=(int)$current['parent_role_id'];
        $available=[];
        foreach($current['items'] as $r)$available[(int)$r['sidebar_item_id']]=$r;
        $submitted=is_array($input['items']??null)?$input['items']:[];

        $grant=$pdo->prepare(
            "INSERT INTO school_sidebar_permission_grants
             (tenant_id,role_id,sidebar_item_id,action_key,is_allowed)
             VALUES(:tenant,:role,:item,:action,:allowed)
             ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed),updated_at=CURRENT_TIMESTAMP"
        );
        $pdo->beginTransaction();
        try{
            foreach($submitted as $row){
                if(!is_array($row))continue;
                $id=(int)($row['sidebar_item_id']??0);
                if(!isset($available[$id]))continue;
                $caps=$available[$id]['caps'];
                $perms=is_array($row['permissions']??null)?$row['permissions']:[];
                $viewRequested=!empty($perms['view']);
                foreach($current['actions'] as $a){
                    $key=(string)$a['action_key'];
                    $allowed=!empty($caps[$key])&&!empty($perms[$key])&&($key==='view'||$viewRequested)?1:0;
                    $grant->execute([
                        'tenant'=>$scope['tenant_id'],'role'=>$roleId,'item'=>$id,
                        'action'=>'parent_delegate_'.$key,'allowed'=>$allowed
                    ]);
                }
            }
            school_sidebar_service_bump_version($pdo,(int)$scope['tenant_id']);
            if(school_sidebar_service_table($pdo,'activity_logs')){
                $log=$pdo->prepare(
                    "INSERT INTO activity_logs
                     (tenant_id,user_id,role_id,module_name,action_key,table_name,description,created_at)
                     VALUES(:tenant,:user,:role,'Parent Sidebar Permission','update',
                            'school_sidebar_permission_grants','Updated Parent Sidebar Permission',CURRENT_TIMESTAMP)"
                );
                $log->execute([
                    'tenant'=>$scope['tenant_id'],'user'=>$scope['user_id']?:null,'role'=>$scope['role_id']?:null
                ]);
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        psp_json(true,'Parent Sidebar Permission saved successfully.',
            ['csrf_token'=>function_exists('csrfToken')?csrfToken():'']);
    }
    psp_json(false,'Invalid action.',[],400);
}catch(Throwable $e){
    $status=(int)$e->getCode();if($status<400||$status>599)$status=500;
    error_log('Parent Sidebar Permission API: '.$e->getMessage());
    psp_json(false,$status>=500?'Unable to process Parent Sidebar Permission.':$e->getMessage(),[],$status);
}

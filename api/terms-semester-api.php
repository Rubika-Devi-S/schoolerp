<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require dirname(__DIR__).'/includes/config.php';

function out($ok,$msg,$data=[]){
 echo json_encode(["success"=>$ok,"message"=>$msg,"data"=>$data]);
 exit;
}

$pdo->exec("
CREATE TABLE IF NOT EXISTS academic_terms(
id BIGINT AUTO_INCREMENT PRIMARY KEY,
tenant_id BIGINT NOT NULL,
branch_id BIGINT NULL,
academic_year_id BIGINT NULL,
term_name VARCHAR(100),
term_code VARCHAR(50),
start_date DATE,
end_date DATE,
description TEXT,
status VARCHAR(20) DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
");

$scope=[
"tenant_id"=>(int)($_SESSION['tenant_id']??0),
"branch_id"=>(int)($_SESSION['branch_id']??0)
];

$input=json_decode(file_get_contents("php://input"),true)??[];
$action=$_GET['action']??$input['action']??'';

if($action=="list"){
$q=$pdo->prepare("SELECT * FROM academic_terms WHERE tenant_id=? ORDER BY id DESC");
$q->execute([$scope['tenant_id']]);
out(true,"Loaded",["terms"=>$q->fetchAll(PDO::FETCH_ASSOC)]);
}

if($action=="get"){
$q=$pdo->prepare("SELECT * FROM academic_terms WHERE id=? AND tenant_id=?");
$q->execute([(int)($_GET['id']??0),$scope['tenant_id']]);
out(true,"Loaded",["term"=>$q->fetch(PDO::FETCH_ASSOC)]);
}

if($action=="save"){
if(!empty($input['id'])){
$q=$pdo->prepare("UPDATE academic_terms SET term_name=?,term_code=?,start_date=?,end_date=?,status=? WHERE id=? AND tenant_id=?");
$q->execute([$input['term_name'],$input['term_code'],$input['start_date'],$input['end_date'],$input['status'],$input['id'],$scope['tenant_id']]);
}else{
$q=$pdo->prepare("INSERT INTO academic_terms(tenant_id,term_name,term_code,start_date,end_date,status) VALUES(?,?,?,?,?,?)");
$q->execute([$scope['tenant_id'],$input['term_name'],$input['term_code'],$input['start_date'],$input['end_date'],$input['status']]);
}
out(true,"Saved");
}

if($action=="delete"){
$q=$pdo->prepare("DELETE FROM academic_terms WHERE id=? AND tenant_id=?");
$q->execute([$input['id'],$scope['tenant_id']]);
out(true,"Deleted");
}

out(false,"Invalid action");
?>

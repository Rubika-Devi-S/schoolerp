<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
if ($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'Method not allowed']);exit;}
if (!validateCsrfToken($_POST['csrf_token']??null)){http_response_code(419);echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);exit;}
$yearId=(int)($_POST['academic_year_id']??0);
$stmt=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
$stmt->execute([$yearId,(int)$_SESSION['tenant_id']]);
$year=$stmt->fetch();
if(!$year){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Academic year not available']);exit;}
$_SESSION['academic_year_id']=(int)$year['id'];
echo json_encode(['success'=>true,'message'=>'Academic year changed']);

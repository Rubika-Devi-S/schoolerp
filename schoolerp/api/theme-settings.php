<?php
declare(strict_types=1);require_once __DIR__.'/../includes/auth.php';require_login();
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(false,'Method not allowed',[],405);
$input=json_decode(file_get_contents('php://input'),true)?:$_POST;
if(!csrf_is_valid($input['csrf_token']??null)) json_response(false,'Invalid CSRF token',[],419);
$allowed=['sidebar_bg','sidebar_text','sidebar_active_bg_1','sidebar_active_bg_2','topbar_bg_1','topbar_bg_2','brand_1','brand_2','layout_density'];
$settings=$input['settings']??[];
if(APP_DEMO_MODE||!$pdo) json_response(true,'Theme preview saved for this demo session',['settings'=>$settings]);
$user=current_user();$pdo->beginTransaction();
try{$stmt=$pdo->prepare('INSERT INTO website_color_settings(tenant_id,setting_key,setting_value,setting_label,setting_group,is_active,updated_by) VALUES(:tenant_id,:setting_key,:setting_value,:setting_key,"theme",1,:updated_by) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),is_active=1,updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');foreach($settings as $k=>$v){if(!in_array($k,$allowed,true))continue;if($k!=='layout_density'&&!preg_match('/^#[0-9a-fA-F]{6}$/',(string)$v))continue;$stmt->execute(['tenant_id'=>$user['tenant_id'],'setting_key'=>$k,'setting_value'=>$v,'updated_by'=>$user['id']]);}$pdo->commit();json_response(true,'Theme settings saved');}catch(Throwable $e){$pdo->rollBack();error_log($e->getMessage());json_response(false,'Unable to save theme settings',[],500);}

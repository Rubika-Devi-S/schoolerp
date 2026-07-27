<?php
declare(strict_types=1);require_once __DIR__.'/includes/bootstrap.php';
if(!empty($_SESSION['user_id'])){header('Location: dashboard.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $username=trim((string)($_POST['username']??'')); $password=(string)($_POST['password']??'');
  if(APP_DEMO_MODE && $username==='admin' && $password==='Admin@123'){
    $_SESSION += ['user_id'=>1,'name'=>'John Admin','role_id'=>1,'role_name'=>'Super Administrator','tenant_id'=>1,'branch_id'=>1];
    header('Location: dashboard.php');exit;
  }
  if(!APP_DEMO_MODE && $pdo){
    $stmt=$pdo->prepare('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=:u AND u.status="active" LIMIT 1');
    $stmt->execute(['u'=>$username]);$u=$stmt->fetch();
    if($u&&password_verify($password,$u['password_hash'])){$_SESSION += ['user_id'=>(int)$u['id'],'name'=>$u['name'],'role_id'=>(int)$u['role_id'],'role_name'=>$u['role_name'],'tenant_id'=>(int)$u['tenant_id'],'branch_id'=>(int)($u['default_branch_id']??1)];header('Location: dashboard.php');exit;}
  }
  $error='Invalid username or password.';
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>

<body class="login-page">
    <form class="login-card" method="post">
        <div class="brand-crest">B</div>
        <h1 class="text-center mt-3">School ERP</h1>
        <p class="text-muted text-center">Client reference UI</p><?php if($error):?><div class="alert alert-danger">
            <?=e($error)?></div><?php endif;?><div class="mb-3"><label>Username</label><input class="form-control"
                name="username" value="admin" required></div>
        <div class="mb-3"><label>Password</label><input class="form-control" type="password" name="password"
                value="Admin@123" required></div><button class="btn btn-primary w-100">Login</button><small
            class="d-block text-center text-muted mt-3">Demo: admin / Admin@123</small>
    </form>
</body>

</html>
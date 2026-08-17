<?php
declare(strict_types=1);

$pageTitle = 'My Profile';
$pageKey = 'profile';
$sidebarFile = __DIR__ . '/sidebar.php';

/* Process profile POST actions before any HTML output so redirects stay safe. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (function_exists('require_login')) {
    require_login();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0);
$current = function_exists('current_user') ? current_user() : [];
if (is_array($current)) {
    $userId = (int)($current['id'] ?? $current['user_id'] ?? $userId);
    $tenantId = (int)($current['tenant_id'] ?? $tenantId);
}

if ($userId <= 0) {
    header('Location: ' . (function_exists('app_url') ? app_url('login.php') : '../login.php'));
    exit;
}

function profileColumn(PDO $pdo, string $table, string $column): bool
{
    if (function_exists('school_column_exists')) {
        return school_column_exists($pdo, $table, $column);
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function profileUrl(string $path): string
{
    $path = ltrim($path, '/');
    return function_exists('app_url') ? app_url($path) : '../' . $path;
}

function profileDeletePhoto(string $relativePath): void
{
    $relativePath = trim($relativePath);
    if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/users/')) return;
    $root = defined('PROJECT_ROOT') ? rtrim((string)PROJECT_ROOT, '/\\') : dirname(__DIR__);
    $absolute = $root . '/' . ltrim($relativePath, '/');
    if (is_file($absolute)) @unlink($absolute);
}

function profileStorePhoto(int $tenantId): ?string
{
    if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) return null;
    $file = $_FILES['profile_photo'];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Unable to upload Profile Photo.');

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Profile Photo must be 2 MB or smaller.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new InvalidArgumentException('Invalid Profile Photo upload.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Profile Photo must be JPG, PNG or WEBP.');
    }

    $root = defined('PROJECT_ROOT') ? rtrim((string)PROJECT_ROOT, '/\\') : dirname(__DIR__);
    $relativeDirectory = 'uploads/users/' . max(1, $tenantId);
    $absoluteDirectory = $root . '/' . $relativeDirectory;
    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('Unable to create Profile Photo folder.');
    }

    $filename = 'profile_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $absolute = $absoluteDirectory . '/' . $filename;
    if (!move_uploaded_file($tmp, $absolute)) {
        throw new RuntimeException('Unable to save Profile Photo.');
    }

    return $relativeDirectory . '/' . $filename;
}

function profileLoad(PDO $pdo, int $userId, int $tenantId): array
{
    $fields = ['u.id', 'u.name', 'u.username'];
    foreach (['email', 'mobile', 'profile_photo', 'photo_path', 'default_branch_id', 'role_id'] as $column) {
        if (profileColumn($pdo, 'users', $column)) $fields[] = 'u.' . $column;
    }

    $roleJoin = profileColumn($pdo, 'users', 'role_id')
        && (function_exists('school_table_exists') ? school_table_exists($pdo, 'roles') : true);
    if ($roleJoin) {
        $fields[] = 'r.role_name';
        $fields[] = 'r.role_key';
    }

    $sql = 'SELECT ' . implode(',', $fields) . ' FROM users u'
        . ($roleJoin ? ' LEFT JOIN roles r ON r.id=u.role_id' : '')
        . ' WHERE u.id=:user_id';
    $params = ['user_id' => $userId];

    if (profileColumn($pdo, 'users', 'tenant_id') && $tenantId > 0) {
        $sql .= ' AND u.tenant_id=:tenant_id';
        $params['tenant_id'] = $tenantId;
    }
    if (profileColumn($pdo, 'users', 'deleted_at')) {
        $sql .= ' AND u.deleted_at IS NULL';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

$profile = profileLoad($pdo, $userId, $tenantId);
if ($profile === []) {
    http_response_code(404);
    exit('User profile was not found.');
}

$messageType = '';
$messageText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (function_exists('csrf_is_valid') && !csrf_is_valid($token)) {
            throw new RuntimeException('Session expired. Refresh the page and try again.');
        }

        $action = trim((string)($_POST['action'] ?? 'update_profile'));

        if ($action === 'update_profile') {
            $name = preg_replace('/\s+/u', ' ', trim((string)($_POST['name'] ?? ''))) ?: '';
            $email = trim((string)($_POST['email'] ?? ''));
            $mobile = preg_replace('/\D+/', '', (string)($_POST['mobile'] ?? '')) ?? '';
            $removePhoto = !empty($_POST['remove_photo']);

            if ($name === '') throw new InvalidArgumentException('Name is required.');
            if (mb_strlen($name) > 120) throw new InvalidArgumentException('Name is too long.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid Email Address.');
            }
            if ($mobile !== '' && (strlen($mobile) < 8 || strlen($mobile) > 15)) {
                throw new InvalidArgumentException('Enter a valid Mobile Number.');
            }

            if ($email !== '' && profileColumn($pdo, 'users', 'email')) {
                $sql = 'SELECT COUNT(*) FROM users WHERE email=:email AND id<>:user_id';
                $params = ['email' => $email, 'user_id' => $userId];
                if (profileColumn($pdo, 'users', 'tenant_id') && $tenantId > 0) {
                    $sql .= ' AND tenant_id=:tenant_id';
                    $params['tenant_id'] = $tenantId;
                }
                if (profileColumn($pdo, 'users', 'deleted_at')) $sql .= ' AND deleted_at IS NULL';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                if ((int)$stmt->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Email Address is already used by another user.');
                }
            }

            $newPhoto = profileStorePhoto($tenantId);
            $oldPhoto = trim((string)($profile['profile_photo'] ?? $profile['photo_path'] ?? ''));
            $sets = ['name=:name'];
            $params = ['name' => $name, 'user_id' => $userId];

            if (profileColumn($pdo, 'users', 'email')) {
                $sets[] = 'email=:email';
                $params['email'] = $email !== '' ? $email : null;
            }
            if (profileColumn($pdo, 'users', 'mobile')) {
                $sets[] = 'mobile=:mobile';
                $params['mobile'] = $mobile !== '' ? $mobile : null;
            }

            $photoColumn = profileColumn($pdo, 'users', 'profile_photo')
                ? 'profile_photo'
                : (profileColumn($pdo, 'users', 'photo_path') ? 'photo_path' : '');
            if ($photoColumn !== '' && ($newPhoto !== null || $removePhoto)) {
                $sets[] = $photoColumn . '=:photo';
                $params['photo'] = $newPhoto;
            }

            $sql = 'UPDATE users SET ' . implode(',', $sets) . ' WHERE id=:user_id';
            if (profileColumn($pdo, 'users', 'tenant_id') && $tenantId > 0) {
                $sql .= ' AND tenant_id=:tenant_id';
                $params['tenant_id'] = $tenantId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (($newPhoto !== null || $removePhoto) && $oldPhoto !== '' && $oldPhoto !== $newPhoto) {
                profileDeletePhoto($oldPhoto);
            }

            $_SESSION['name'] = $name;
            if ($photoColumn !== '') {
                $_SESSION['profile_photo'] = $newPhoto ?? ($removePhoto ? '' : $oldPhoto);
                $_SESSION['photo_path'] = $_SESSION['profile_photo'];
            }
            $_SESSION['success_message'] = 'Profile updated successfully.';

            header('Location: ' . profileUrl('school/profile.php'));
            exit;
        }

        if ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if ($currentPassword === '') throw new InvalidArgumentException('Current Password is required.');
            if (strlen($newPassword) < 8) throw new InvalidArgumentException('New Password must contain at least 8 characters.');
            if ($newPassword !== $confirmPassword) throw new InvalidArgumentException('New Password and Confirm Password do not match.');

            $passwordColumn = profileColumn($pdo, 'users', 'password_hash')
                ? 'password_hash'
                : (profileColumn($pdo, 'users', 'password') ? 'password' : '');
            if ($passwordColumn === '') throw new RuntimeException('Password column is unavailable.');

            $passwordSql = "SELECT {$passwordColumn} FROM users WHERE id=:user_id";
            $passwordParams = ['user_id' => $userId];
            if (profileColumn($pdo, 'users', 'tenant_id') && $tenantId > 0) {
                $passwordSql .= ' AND tenant_id=:tenant_id';
                $passwordParams['tenant_id'] = $tenantId;
            }
            $passwordSql .= ' LIMIT 1';
            $stmt = $pdo->prepare($passwordSql);
            $stmt->execute($passwordParams);
            $stored = (string)$stmt->fetchColumn();
            if ($stored === '' || !password_verify($currentPassword, $stored)) {
                throw new InvalidArgumentException('Current Password is incorrect.');
            }

            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            if ($hash === false) throw new RuntimeException('Unable to secure the new password.');

            $passwordUpdateSql = "UPDATE users SET {$passwordColumn}=:password WHERE id=:user_id";
            $passwordUpdateParams = ['password' => $hash, 'user_id' => $userId];
            if (profileColumn($pdo, 'users', 'tenant_id') && $tenantId > 0) {
                $passwordUpdateSql .= ' AND tenant_id=:tenant_id';
                $passwordUpdateParams['tenant_id'] = $tenantId;
            }
            $stmt = $pdo->prepare($passwordUpdateSql);
            $stmt->execute($passwordUpdateParams);

            $_SESSION['success_message'] = 'Password changed successfully.';
            header('Location: ' . profileUrl('school/profile.php'));
            exit;
        }

        throw new InvalidArgumentException('Invalid Profile action.');
    } catch (Throwable $e) {
        $messageType = 'error';
        $messageText = $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Unable to update your profile.';
        error_log('school/profile.php: ' . $e->getMessage());
        $profile = profileLoad($pdo, $userId, $tenantId);
    }
}

$photoPath = trim((string)($profile['profile_photo'] ?? $profile['photo_path'] ?? ''));
$photoUrl = $photoPath !== '' ? profileUrl($photoPath) : '';
$roleName = trim((string)($profile['role_name'] ?? $current['role_name'] ?? 'User'));
$csrf = function_exists('csrfToken') ? csrfToken() : '';

/* Render the normal School ERP layout only after all redirect-capable actions. */
require dirname(__DIR__) . '/includes/layout-start.php';
?>

<style>
.profile-page{display:grid;gap:16px;max-width:1180px;margin:0 auto}.profile-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}.profile-head h1{margin:0;font-size:30px;font-weight:800;color:#101a3b}.profile-head p{margin:5px 0 0;color:#64748b;font-size:13px}.profile-grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px}.profile-card{background:#fff;border:1px solid #e5eaf2;border-radius:16px;box-shadow:0 4px 18px rgba(15,23,42,.04)}.profile-card-body{padding:20px}.profile-avatar-wrap{text-align:center}.profile-avatar{width:126px;height:126px;margin:4px auto 14px;border-radius:50%;display:grid;place-items:center;overflow:hidden;background:linear-gradient(135deg,#6d4ce7,#315ed8);color:#fff;font-size:34px;font-weight:800;box-shadow:0 10px 28px rgba(79,70,229,.2)}.profile-avatar img{width:100%;height:100%;object-fit:cover}.profile-avatar-wrap h2{font-size:18px;margin:0;color:#101a3b}.profile-avatar-wrap p{margin:4px 0 0;color:#64748b;font-size:12px}.profile-meta{display:grid;gap:10px;margin-top:20px}.profile-meta div{padding:11px 12px;border:1px solid #edf1f6;border-radius:10px;background:#fafbff}.profile-meta small,.profile-meta strong{display:block}.profile-meta small{font-size:10px;color:#64748b}.profile-meta strong{font-size:12px;color:#172554;margin-top:3px;word-break:break-word}.profile-section-title{font-size:16px;font-weight:800;color:#101a3b;margin:0 0 16px}.profile-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.profile-field.full{grid-column:1/-1}.profile-field label{display:block;margin-bottom:6px;font-size:11px;font-weight:750;color:#334155}.profile-field input{width:100%;min-height:43px;border:1px solid #dfe5ee;border-radius:10px;padding:0 12px;background:#fff;color:#172554}.profile-field input:focus{outline:0;border-color:#818cf8;box-shadow:0 0 0 3px rgba(99,102,241,.12)}.profile-help{font-size:10px;color:#64748b;margin-top:5px}.profile-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:18px}.profile-btn{min-height:42px;padding:0 16px;border:1px solid #dfe5ee;border-radius:10px;background:#fff;color:#172554;font-size:12px;font-weight:750;display:inline-flex;align-items:center;gap:7px;justify-content:center}.profile-btn.primary{background:#4f46e5;border-color:#4f46e5;color:#fff}.profile-photo-actions{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:12px}.profile-photo-label{cursor:pointer}.profile-photo-label input{display:none}.profile-remove{display:flex;align-items:center;gap:6px;justify-content:center;font-size:11px;color:#64748b;margin-top:10px}.profile-preview{display:none;margin-top:10px;color:#4f46e5;font-size:10px;font-weight:700}.profile-preview.show{display:block}@media(max-width:900px){.profile-grid{grid-template-columns:1fr}.profile-card:first-child{order:-1}}@media(max-width:600px){.profile-head{display:block}.profile-form-grid{grid-template-columns:1fr}.profile-field.full{grid-column:auto}.profile-actions{display:grid}.profile-btn{width:100%}}
</style>

<div class="profile-page">
    <div class="profile-head">
        <div>
            <h1>My Profile</h1>
            <p>Manage your account information, profile photo and password.</p>
        </div>
    </div>

    <div class="profile-grid">
        <aside class="profile-card">
            <div class="profile-card-body profile-avatar-wrap">
                <div class="profile-avatar" id="profileAvatar">
                    <?php if ($photoPath !== '' && is_file(dirname(__DIR__) . '/' . ltrim($photoPath, '/'))): ?>
                        <img id="profileAvatarImage" src="<?= e($photoUrl) ?>" alt="<?= e((string)$profile['name']) ?>">
                    <?php else: ?>
                        <span id="profileAvatarInitials"><?= e(function_exists('user_initials') ? user_initials((string)$profile['name']) : 'U') ?></span>
                    <?php endif; ?>
                </div>
                <h2><?= e((string)($profile['name'] ?? 'User')) ?></h2>
                <p><?= e($roleName) ?></p>

                <div class="profile-meta">
                    <div><small>Username</small><strong><?= e((string)($profile['username'] ?? '-')) ?></strong></div>
                    <div><small>Email</small><strong><?= e((string)($profile['email'] ?? '-')) ?></strong></div>
                    <div><small>Mobile</small><strong><?= e((string)($profile['mobile'] ?? '-')) ?></strong></div>
                </div>
            </div>
        </aside>

        <div style="display:grid;gap:16px">
            <section class="profile-card">
                <div class="profile-card-body">
                    <h2 class="profile-section-title">Profile Information</h2>
                    <form method="post" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                        <div class="profile-form-grid">
                            <div class="profile-field">
                                <label>Full Name *</label>
                                <input name="name" maxlength="120" required value="<?= e((string)($profile['name'] ?? '')) ?>">
                            </div>
                            <div class="profile-field">
                                <label>Username</label>
                                <input value="<?= e((string)($profile['username'] ?? '')) ?>" readonly>
                                <div class="profile-help">Username is managed from User Management.</div>
                            </div>
                            <div class="profile-field">
                                <label>Email Address</label>
                                <input name="email" type="email" maxlength="190" value="<?= e((string)($profile['email'] ?? '')) ?>">
                            </div>
                            <div class="profile-field">
                                <label>Mobile Number</label>
                                <input name="mobile" inputmode="numeric" maxlength="15" value="<?= e((string)($profile['mobile'] ?? '')) ?>">
                            </div>
                            <div class="profile-field full">
                                <label>Profile Photo</label>
                                <input id="profilePhoto" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp">
                                <div class="profile-help">JPG, PNG or WEBP. Maximum file size 2 MB.</div>
                                <div class="profile-preview" id="profilePreviewText">New photo selected. Save Profile to apply it.</div>
                                <?php if ($photoPath !== ''): ?>
                                    <label class="profile-remove"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="profile-actions">
                            <button class="profile-btn primary" type="submit"><i data-lucide="save"></i> Save Profile</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="profile-card">
                <div class="profile-card-body">
                    <h2 class="profile-section-title">Change Password</h2>
                    <form method="post" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                        <div class="profile-form-grid">
                            <div class="profile-field full">
                                <label>Current Password *</label>
                                <input name="current_password" type="password" autocomplete="current-password" required>
                            </div>
                            <div class="profile-field">
                                <label>New Password *</label>
                                <input name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                            </div>
                            <div class="profile-field">
                                <label>Confirm New Password *</label>
                                <input name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>

                        <div class="profile-actions">
                            <button class="profile-btn primary" type="submit"><i data-lucide="key-round"></i> Change Password</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(() => {
    const type = <?= json_encode($messageType, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const text = <?= json_encode($messageText, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    if (text) {
        if (typeof window.showToast === 'function') window.showToast(type || 'error', text);
        else if (typeof window.schoolToast === 'function') window.schoolToast(type || 'error', text);
    }

    const input = document.getElementById('profilePhoto');
    const avatar = document.getElementById('profileAvatar');
    const previewText = document.getElementById('profilePreviewText');
    if (input && avatar) {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                input.value = '';
                if (typeof window.showToast === 'function') window.showToast('warning', 'Profile Photo must be 2 MB or smaller.');
                return;
            }
            const url = URL.createObjectURL(file);
            avatar.innerHTML = `<img src="${url}" alt="Profile preview" style="width:100%;height:100%;object-fit:cover">`;
            previewText?.classList.add('show');
        });
    }

    const passwordForm = document.getElementById('passwordForm');
    passwordForm?.addEventListener('submit', event => {
        const a = passwordForm.querySelector('[name="new_password"]')?.value || '';
        const b = passwordForm.querySelector('[name="confirm_password"]')?.value || '';
        if (a !== b) {
            event.preventDefault();
            if (typeof window.showToast === 'function') window.showToast('warning', 'New Password and Confirm Password do not match.');
        }
    });

    window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

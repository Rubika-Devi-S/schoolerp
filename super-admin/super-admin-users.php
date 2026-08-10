<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/bootstrap.php';

if (function_exists('require_login')) {
    require_login();
}

$pageTitle = 'Create Super Admin User';
$pageKey = 'super_admin_users';
$sidebarFile = __DIR__ . '/sidebar.php';

$currentUser = function_exists('current_user')
    ? current_user()
    : [];

$roleId = (int)(
    $currentUser['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$roleKey = strtolower(trim((string)(
    $currentUser['role_key']
    ?? $_SESSION['role_key']
    ?? ''
)));

if (
    $roleKey === ''
    && isset($pdo)
    && $pdo instanceof PDO
    && $roleId > 0
) {
    try {
        $roleStatement = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :role_id
               AND status = 'active'
             LIMIT 1"
        );

        $roleStatement->execute([
            'role_id' => $roleId,
        ]);

        $roleKey = strtolower(trim(
            (string)$roleStatement->fetchColumn()
        ));
    } catch (Throwable $exception) {
        error_log(
            'Super Admin role lookup failed: '
            . $exception->getMessage()
        );
    }
}

$isSuperAdmin = $roleId === 1
    || in_array(
        $roleKey,
        [
            'super_admin',
            'super-administrator',
            'super_administrator',
        ],
        true
    );

if (!$isSuperAdmin) {
    http_response_code(403);

    require $projectRoot . '/includes/layout-start.php';
    ?>
    <div class="ui-card">
        <div class="ui-card-body">
            <h1 class="page-title">Access denied</h1>
            <p class="page-subtitle">
                Only a Super Administrator can create users.
            </p>
        </div>
    </div>
    <?php
    require $projectRoot . '/includes/layout-end.php';
    exit;
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

if (function_exists('csrfToken')) {
    $csrfToken = (string)csrfToken();
} elseif (function_exists('csrf_token')) {
    $csrfToken = (string)csrf_token();
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    $csrfToken = (string)$_SESSION['csrf_token'];
}

require $projectRoot . '/includes/layout-start.php';
?>

<style>
.user-create-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 310px;
    gap: 16px;
}

.user-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.user-form-field.full {
    grid-column: 1 / -1;
}

.user-form-field label {
    display: block;
    margin-bottom: 7px;
    font-size: 11px;
    font-weight: 750;
}

.user-required {
    color: var(--danger-color, #dc3545);
}

.user-form-help {
    display: block;
    margin-top: 6px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
    line-height: 1.5;
}

.user-photo-box {
    display: grid;
    gap: 14px;
    justify-items: center;
    padding: 18px;
    border: 1px dashed var(--border-soft, #e7ebf3);
    border-radius: 13px;
    background: var(--body-bg, #f6f8fc);
    text-align: center;
}

.user-photo-preview {
    width: 112px;
    height: 112px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    color: var(--brand-1, #6747e8);
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
}

.user-photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-photo-preview svg {
    width: 38px;
    height: 38px;
}

.user-security-list {
    display: grid;
    gap: 10px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.user-security-list li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
    line-height: 1.5;
}

.user-security-list svg {
    width: 15px;
    min-width: 15px;
    margin-top: 1px;
    color: var(--success-color, #21ae71);
}

.user-submit-row {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 4px;
}

.user-message {
    display: none;
    margin: 0 0 16px;
}

.user-message.show {
    display: block;
}

@media (max-width: 991.98px) {
    .user-create-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .user-form-grid {
        grid-template-columns: 1fr;
    }

    .user-form-field.full {
        grid-column: auto;
    }
}

@media (max-width: 575.98px) {
    .user-submit-row {
        flex-direction: column-reverse;
    }

    .user-submit-row .btn-ui {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">Create User</h1>
        <p class="page-subtitle">
            Add a user and assign the appropriate branch and role.
        </p>
    </div>

    <div class="page-actions">
        <a
            id="usersListButton"
            class="btn-ui"
            href="<?= e($baseUrl . 'super-admin/users.php') ?>"
        >
            <i data-lucide="arrow-left"></i>
            Users List
        </a>
    </div>
</div>

<div
    id="userMessage"
    class="alert user-message"
    role="alert"
></div>

<form
    id="createUserForm"
    method="post"
    enctype="multipart/form-data"
    novalidate
>
    <input
        type="hidden"
        name="action"
        value="create_super_admin_user"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($csrfToken) ?>"
    >

    <div class="user-create-grid">
        <article class="ui-card">
            <div class="ui-card-header">
                <h2 class="ui-card-title">User Information</h2>
                <i data-lucide="user-plus"></i>
            </div>

            <div class="ui-card-body">
                <div class="user-form-grid">
                    <div class="user-form-field">
                        <label for="name">
                            Full Name
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="name"
                            name="name"
                            class="form-control"
                            type="text"
                            minlength="2"
                            maxlength="150"
                            autocomplete="name"
                            required
                        >

                        <div class="invalid-feedback">
                            Enter the user's full name.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="username">
                            Username
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="username"
                            name="username"
                            class="form-control"
                            type="text"
                            minlength="3"
                            maxlength="100"
                            pattern="[A-Za-z0-9._-]{3,100}"
                            autocomplete="username"
                            required
                        >

                        <small class="user-form-help">
                            Letters, numbers, dots, underscores and hyphens.
                        </small>

                        <div class="invalid-feedback">
                            Enter a valid username.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="email">
                            Email Address
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="email"
                            name="email"
                            class="form-control"
                            type="email"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >

                        <div class="invalid-feedback">
                            Enter a valid email address.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="mobile">
                            Mobile Number
                        </label>

                        <input
                            id="mobile"
                            name="mobile"
                            class="form-control"
                            type="tel"
                            maxlength="20"
                            autocomplete="tel"
                            placeholder="+91 98765 43210"
                        >

                        <div class="invalid-feedback">
                            Enter a valid mobile number.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="password">
                            Password
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="password"
                            name="password"
                            class="form-control"
                            type="password"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <small class="user-form-help">
                            Minimum 8 characters with uppercase,
                            lowercase and a number.
                        </small>

                        <div class="invalid-feedback">
                            Enter a strong password.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="confirm_password">
                            Confirm Password
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            type="password"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <div class="invalid-feedback">
                            Password confirmation must match.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="branch_id">
                            Branch
                            <span class="user-required">*</span>
                        </label>

                        <select
                            id="branch_id"
                            name="branch_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Loading branches...
                            </option>
                        </select>

                        <div class="invalid-feedback">
                            Select a branch.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="role_id">
                            Role
                            <span class="user-required">*</span>
                        </label>

                        <select
                            id="role_id"
                            name="role_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Loading roles...
                            </option>
                        </select>

                        <div class="invalid-feedback">
                            Select a role available for the branch.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="status">
                            Status
                            <span class="user-required">*</span>
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                            required
                        >
                            <option value="active" selected>
                                Active
                            </option>

                            <option value="inactive">
                                Inactive
                            </option>
                        </select>
                    </div>

                    <div class="user-form-field full">
                        <div class="user-submit-row">
                            <a
                                id="cancelButton"
                                href="<?= e(
                                    $baseUrl
                                    . 'super-admin/users.php'
                                ) ?>"
                                class="btn-ui"
                            >
                                Cancel
                            </a>

                            <button
                                id="submitButton"
                                class="btn-ui btn-primary-ui"
                                type="submit"
                            >
                                <i data-lucide="user-plus"></i>
                                Create User
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <div>
            <article class="ui-card">
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Profile Photo</h2>
                    <i data-lucide="image-up"></i>
                </div>

                <div class="ui-card-body">
                    <div class="user-photo-box">
                        <div
                            id="photoPreview"
                            class="user-photo-preview"
                        >
                            <i data-lucide="user-round"></i>
                        </div>

                        <div>
                            <label
                                for="profile_photo"
                                class="btn-ui"
                            >
                                <i data-lucide="upload"></i>
                                Choose Photo
                            </label>

                            <input
                                id="profile_photo"
                                name="profile_photo"
                                class="d-none"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >
                        </div>

                        <small class="user-form-help">
                            JPG, PNG or WebP. Maximum file size: 2 MB.
                        </small>
                    </div>
                </div>
            </article>

            <article class="ui-card mt-3">
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Security</h2>
                    <i data-lucide="shield-check"></i>
                </div>

                <div class="ui-card-body">
                    <ul class="user-security-list">
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            Username and email are checked before saving.
                        </li>
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            New user passwords are stored using BCRYPT.
                        </li>
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            The selected role is validated against the branch.
                        </li>
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            Profile photos are type and size validated.
                        </li>
                    </ul>
                </div>
            </article>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';

    const apiUrl = <?= json_encode(
        $baseUrl . 'api/auth.php',
        JSON_UNESCAPED_SLASHES
    ) ?>;

    let csrfToken = <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_SLASHES
    ) ?>;

    let roles = [];

    const form = document.getElementById('createUserForm');
    const branchSelect = document.getElementById('branch_id');
    const roleSelect = document.getElementById('role_id');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById(
        'confirm_password'
    );
    const mobile = document.getElementById('mobile');
    const photoInput = document.getElementById('profile_photo');
    const photoPreview = document.getElementById('photoPreview');
    const submitButton = document.getElementById('submitButton');
    const messageBox = document.getElementById('userMessage');
    const usersListButton = document.getElementById(
        'usersListButton'
    );
    const cancelButton = document.getElementById('cancelButton');

    function showMessage(text, success) {
        messageBox.className =
            'alert user-message show '
            + (success ? 'alert-success' : 'alert-danger');

        messageBox.textContent = text;
        messageBox.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    async function readJson(response) {
        const text = await response.text();

        if (text.trim() === '') {
            throw new Error('The API returned an empty response.');
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('Invalid API response:', text);

            throw new Error(
                'The API returned invalid JSON. Check the PHP error log.'
            );
        }
    }

    function renderBranches(branches) {
        branchSelect.innerHTML =
            '<option value="">Select branch</option>';

        branches.forEach(branch => {
            const option = document.createElement('option');

            option.value = String(branch.id);
            option.dataset.tenantId = String(branch.tenant_id);
            option.textContent =
                branch.branch_name
                + ' (' + branch.branch_code + ')';

            branchSelect.appendChild(option);
        });
    }

    function renderRoles() {
        const selectedBranch = branchSelect.selectedOptions[0];
        const tenantId = selectedBranch
            ? selectedBranch.dataset.tenantId || ''
            : '';

        roleSelect.innerHTML =
            '<option value="">Select role</option>';

        roles.forEach(role => {
            const roleTenantId = role.tenant_id === null
                ? ''
                : String(role.tenant_id);

            if (
                roleTenantId !== ''
                && tenantId !== ''
                && roleTenantId !== tenantId
            ) {
                return;
            }

            const option = document.createElement('option');

            option.value = String(role.id);
            option.textContent =
                role.role_name
                + ' (' + role.role_key + ')';

            roleSelect.appendChild(option);
        });
    }

    async function loadMeta() {
        branchSelect.disabled = true;
        roleSelect.disabled = true;

        try {
            const url = new URL(apiUrl, window.location.origin);
            url.searchParams.set(
                'action',
                'super_admin_user_meta'
            );

            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await readJson(response);

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message
                    || 'Unable to load branches and roles.'
                );
            }

            csrfToken =
                result.data.csrf_token || csrfToken;

            form.querySelector(
                '[name="csrf_token"]'
            ).value = csrfToken;

            roles = Array.isArray(result.data.roles)
                ? result.data.roles
                : [];

            renderBranches(
                Array.isArray(result.data.branches)
                    ? result.data.branches
                    : []
            );

            renderRoles();

            if (result.data.users_list_url) {
                usersListButton.href =
                    result.data.users_list_url;

                cancelButton.href =
                    result.data.users_list_url;
            }
        } catch (error) {
            showMessage(
                error instanceof Error
                    ? error.message
                    : 'Unable to load form details.',
                false
            );
        } finally {
            branchSelect.disabled = false;
            roleSelect.disabled = false;
        }
    }

    function validatePasswordConfirmation() {
        if (
            confirmPassword.value !== ''
            && password.value !== confirmPassword.value
        ) {
            confirmPassword.setCustomValidity(
                'Password confirmation does not match.'
            );
        } else {
            confirmPassword.setCustomValidity('');
        }
    }

    function validateStrongPassword() {
        const value = password.value;

        const valid = value === ''
            || (
                value.length >= 8
                && /[A-Z]/.test(value)
                && /[a-z]/.test(value)
                && /[0-9]/.test(value)
            );

        password.setCustomValidity(
            valid
                ? ''
                : 'Use uppercase, lowercase and a number.'
        );

        validatePasswordConfirmation();
    }

    function validateMobile() {
        const value = mobile.value.trim();

        mobile.setCustomValidity(
            value === ''
            || /^\+?[0-9][0-9\s-]{6,19}$/.test(value)
                ? ''
                : 'Enter a valid mobile number.'
        );
    }

    branchSelect.addEventListener('change', renderRoles);
    password.addEventListener('input', validateStrongPassword);
    confirmPassword.addEventListener(
        'input',
        validatePasswordConfirmation
    );
    mobile.addEventListener('input', validateMobile);

    photoInput.addEventListener('change', () => {
        const file = photoInput.files
            ? photoInput.files[0]
            : null;

        if (!file) {
            photoPreview.innerHTML =
                '<i data-lucide="user-round"></i>';

            if (window.lucide) {
                window.lucide.createIcons();
            }

            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (
            !allowedTypes.includes(file.type)
            || file.size > 2 * 1024 * 1024
        ) {
            photoInput.value = '';
            photoPreview.innerHTML =
                '<i data-lucide="user-round"></i>';

            photoInput.setCustomValidity(
                'Choose a JPG, PNG or WebP file below 2 MB.'
            );

            if (window.lucide) {
                window.lucide.createIcons();
            }

            return;
        }

        photoInput.setCustomValidity('');

        const reader = new FileReader();

        reader.addEventListener('load', event => {
            const image = document.createElement('img');

            image.src = String(event.target.result || '');
            image.alt = 'Profile preview';

            photoPreview.replaceChildren(image);
        });

        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        validateStrongPassword();
        validatePasswordConfirmation();
        validateMobile();

        form.classList.add('was-validated');

        if (!form.checkValidity()) {
            return;
        }

        submitButton.disabled = true;

        try {
            const formData = new FormData(form);

            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await readJson(response);

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Unable to create the user.'
                );
            }

            showMessage(result.message, true);

            window.setTimeout(() => {
                window.location.href =
                    result.data.redirect
                    || usersListButton.href;
            }, 500);
        } catch (error) {
            showMessage(
                error instanceof Error
                    ? error.message
                    : 'Unable to create the user.',
                false
            );
        } finally {
            submitButton.disabled = false;
        }
    });

    loadMeta();
})();
</script>

<?php require $projectRoot . '/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header(
        'Location: '
        . school_current_role_dashboard_url()
    );
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginIdentifier = trim((string)(
        $_POST['username']
        ?? ''
    ));

    $password = (string)(
        $_POST['password']
        ?? ''
    );

    $csrfToken = trim((string)(
        $_POST['csrf_token']
        ?? ''
    ));

    if (!csrf_is_valid($csrfToken)) {
        $error = 'Your login session expired. Please try again.';
    } elseif (
        $loginIdentifier === ''
        || $password === ''
    ) {
        $error = 'Invalid username or password.';
    } elseif (!($pdo instanceof PDO)) {
        $error = 'Database connection is unavailable.';
    } else {
        try {
            /*
             * Optional multi-school identifier:
             * BPS001/admin
             *
             * The existing Username field and UI remain unchanged. The
             * school-code prefix is only needed when the same username exists
             * in more than one school.
             */
            $schoolCode = '';
            $identity = $loginIdentifier;

            if (str_contains($loginIdentifier, '/')) {
                [$schoolCode, $identity] = array_pad(
                    explode('/', $loginIdentifier, 2),
                    2,
                    ''
                );

                $schoolCode = trim($schoolCode);
                $identity = trim($identity);
            }

            $sql = "SELECT
                        u.id,
                        u.tenant_id,
                        u.default_branch_id,
                        u.role_id,
                        u.name,
                        u.email,
                        u.mobile,
                        u.username,
                        u.password_hash,
                        u.profile_photo,
                        u.status AS user_status,
                        r.role_key,
                        r.role_name,
                        r.status AS role_status,
                        t.tenant_code,
                        t.school_name,
                        t.status AS school_status,
                        b.branch_code,
                        b.branch_name,
                        b.status AS branch_status
                    FROM users AS u
                    INNER JOIN roles AS r
                        ON r.id = u.role_id
                    INNER JOIN tenants AS t
                        ON t.id = u.tenant_id
                    LEFT JOIN branches AS b
                        ON b.id = u.default_branch_id
                    WHERE (
                        LOWER(u.username) = LOWER(:username_identity)
                        OR LOWER(COALESCE(u.email, ''))
                           = LOWER(:email_identity)
                    )";

            $parameters = [
                'username_identity' => $identity,
                'email_identity' => $identity,
            ];

            if ($schoolCode !== '') {
                $sql .= "
                    AND LOWER(t.tenant_code)
                        = LOWER(:school_code)";

                $parameters['school_code'] = $schoolCode;
            }

            $sql .= "
                ORDER BY u.id
                LIMIT 20";

            $statement = $pdo->prepare($sql);
            $statement->execute($parameters);

            $candidates = $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

            $matchedUsers = [];

            foreach ($candidates as $candidate) {
                if (
                    password_verify(
                        $password,
                        (string)$candidate['password_hash']
                    )
                ) {
                    $matchedUsers[] = $candidate;
                }
            }

            if (count($matchedUsers) !== 1) {
                /*
                 * A generic response prevents username, email and school
                 * enumeration. When duplicate usernames exist across schools,
                 * users can log in with SCHOOLCODE/username.
                 */
                $error = 'Invalid username or password.';
            } else {
                $user = $matchedUsers[0];

                $roleKey = school_normalize_role_key(
                    (string)$user['role_key'],
                    (string)$user['role_name']
                );

                $isSuperAdmin = $roleKey === 'super_admin';

                $accountActive =
                    strtolower((string)$user['user_status'])
                        === 'active'
                    && strtolower((string)$user['role_status'])
                        === 'active'
                    && in_array(
                        strtolower((string)$user['school_status']),
                        ['trial', 'active'],
                        true
                    );

                $branchActive = $isSuperAdmin
                    || $user['default_branch_id'] === null
                    || strtolower((string)$user['branch_status'])
                        === 'active';

                if (!$accountActive || !$branchActive) {
                    $error = 'Invalid username or password.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION = array_merge(
                        $_SESSION,
                        [
                            'user_id' => (int)$user['id'],
                            'name' => (string)$user['name'],
                            'username' =>
                                (string)$user['username'],
                            'email' => (string)(
                                $user['email']
                                ?? ''
                            ),
                            'mobile' => (string)(
                                $user['mobile']
                                ?? ''
                            ),
                            'role_id' => (int)$user['role_id'],
                            'role_key' => $roleKey,
                            'role_name' =>
                                (string)$user['role_name'],
                            /*
                             * school_id is the security scope used by all
                             * school-owned queries. tenant_id is preserved as
                             * a compatibility alias for the current schema.
                             */
                            'school_id' =>
                                (int)$user['tenant_id'],
                            'tenant_id' =>
                                (int)$user['tenant_id'],
                            'branch_id' =>
                                $user['default_branch_id']
                                    !== null
                                    ? (int)$user['default_branch_id']
                                    : 0,
                            'default_branch_id' =>
                                $user['default_branch_id']
                                    !== null
                                    ? (int)$user['default_branch_id']
                                    : 0,
                            'school_code' =>
                                (string)$user['tenant_code'],
                            'school_name' =>
                                (string)$user['school_name'],
                            'branch_code' => (string)(
                                $user['branch_code']
                                ?? ''
                            ),
                            'branch_name' => (string)(
                                $user['branch_name']
                                ?? ''
                            ),
                            'profile_photo' => (string)(
                                $user['profile_photo']
                                ?? ''
                            ),
                            'logged_in_at' =>
                                date('Y-m-d H:i:s'),
                        ]
                    );

                    /*
                     * A Super Admin is cross-school. School data remains
                     * unavailable until an explicit active school context is
                     * selected from the Super Admin panel.
                     */
                    if ($isSuperAdmin) {
                        unset($_SESSION['active_school_id']);
                    } else {
                        $_SESSION['active_school_id'] =
                            (int)$user['tenant_id'];
                    }

                    $pdo->beginTransaction();

                    $updateStatement = $pdo->prepare(
                        "UPDATE users
                         SET last_login_at = NOW()
                         WHERE id = :user_id
                           AND tenant_id = :school_id"
                    );

                    $updateStatement->execute([
                        'user_id' => (int)$user['id'],
                        'school_id' =>
                            (int)$user['tenant_id'],
                    ]);

                    if (school_table_exists(
                        $pdo,
                        'login_logs'
                    )) {
                        $logStatement = $pdo->prepare(
                            "INSERT INTO login_logs (
                                tenant_id,
                                user_id,
                                login_at,
                                ip_address,
                                user_agent,
                                login_status
                            ) VALUES (
                                :school_id,
                                :user_id,
                                NOW(),
                                :ip_address,
                                :user_agent,
                                'success'
                            )"
                        );

                        $logStatement->execute([
                            'school_id' =>
                                (int)$user['tenant_id'],
                            'user_id' => (int)$user['id'],
                            'ip_address' => substr(
                                (string)(
                                    $_SERVER['REMOTE_ADDR']
                                    ?? ''
                                ),
                                0,
                                45
                            ) ?: null,
                            'user_agent' => substr(
                                (string)(
                                    $_SERVER['HTTP_USER_AGENT']
                                    ?? ''
                                ),
                                0,
                                1000
                            ) ?: null,
                        ]);
                    }

                    $pdo->commit();

                    header(
                        'Location: '
                        . school_current_role_dashboard_url()
                    );
                    exit;
                }
            }
        } catch (Throwable $exception) {
            if (
                $pdo instanceof PDO
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            error_log(
                'Login error: '
                . $exception->getMessage()
            );

            $error = 'Unable to complete login.';
        }
    }
}

$loginCsrfToken = csrfToken();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta
    name="viewport"
    content="width=device-width,initial-scale=1,viewport-fit=cover"
>
<title>School ERP Login | ECOMMER</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>
<link href="assets/css/app.css" rel="stylesheet">

<style>
:root{
    --erp-violet:#5a20ec;
    --erp-violet-2:#6d3af5;
    --erp-blue:#2f67df;
    --erp-cyan:#14c9d3;
    --erp-navy:#142553;
    --erp-text:#2c3a59;
    --erp-muted:#728099;
    --erp-line:#dfe6f1;
    --erp-page:#f5f7fb;
    --erp-white:#ffffff;
    --erp-shadow:
        0 24px 80px rgba(26,50,91,.14),
        0 4px 18px rgba(26,50,91,.06);
}

*{
    box-sizing:border-box;
}

html,
body{
    width:100%;
    min-width:0;
    min-height:100%;
    margin:0;
}

body.erp-login-page{
    min-height:100vh;
    min-height:100dvh;
    margin:0;
    color:var(--erp-text);
    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
    background:
        radial-gradient(
            circle at 16% 10%,
            rgba(106,74,247,.08),
            transparent 29%
        ),
        radial-gradient(
            circle at 88% 88%,
            rgba(20,201,211,.08),
            transparent 31%
        ),
        var(--erp-page);
    overflow-x:hidden;
}

body.erp-intro-active{
    overflow:hidden;
}

/* =========================================================
   INTRO
   ========================================================= */

.erp-intro{
    position:fixed;
    inset:0;
    z-index:50000;
    display:grid;
    place-items:center;
    padding:24px;
    background:#fff;
    transition:
        opacity .55s ease,
        visibility .55s ease;
}

.erp-intro.is-hidden{
    opacity:0;
    visibility:hidden;
    pointer-events:none;
}

.erp-intro-inner{
    width:min(760px,92vw);
    text-align:center;
}

.erp-intro-logo{
    display:block;
    width:min(660px,88vw);
    height:auto;
    margin:0 auto;
    mix-blend-mode:multiply;
    opacity:0;
    transform:translateY(24px) scale(.94);
    animation:
        erpIntroLogo .85s
        cubic-bezier(.16,1,.3,1)
        forwards;
}

.erp-intro-caption{
    margin-top:16px;
    color:#77849b;
    font-size:12px;
    font-weight:850;
    letter-spacing:.19em;
    text-transform:uppercase;
    opacity:0;
    transform:translateY(8px);
    animation:erpIntroCaption .55s .38s ease forwards;
}

.erp-intro-progress{
    width:min(300px,64vw);
    height:4px;
    margin:25px auto 0;
    overflow:hidden;
    border-radius:999px;
    background:#edf1f6;
}

.erp-intro-progress span{
    display:block;
    width:100%;
    height:100%;
    border-radius:inherit;
    transform:translateX(-100%);
    background:
        linear-gradient(
            90deg,
            var(--erp-violet),
            var(--erp-blue),
            var(--erp-cyan)
        );
    animation:erpIntroProgress 1.8s .12s ease forwards;
}

@keyframes erpIntroLogo{
    to{
        opacity:1;
        transform:none;
    }
}

@keyframes erpIntroCaption{
    to{
        opacity:1;
        transform:none;
    }
}

@keyframes erpIntroProgress{
    to{
        transform:translateX(0);
    }
}

/* =========================================================
   PAGE
   ========================================================= */

.erp-login-stage{
    position:relative;
    z-index:1;
    width:100%;
    min-height:100vh;
    min-height:100dvh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:
        max(24px,env(safe-area-inset-top))
        max(28px,env(safe-area-inset-right))
        max(18px,env(safe-area-inset-bottom))
        max(28px,env(safe-area-inset-left));
    opacity:0;
    transform:translateY(12px);
    transition:
        opacity .7s ease,
        transform .7s
        cubic-bezier(.16,1,.3,1);
}

body.erp-login-ready .erp-login-stage{
    opacity:1;
    transform:none;
}

.erp-login-wrap{
    width:min(1460px,100%);
}

.erp-shell{
    position:relative;
    width:100%;
    min-height:min(820px,calc(100dvh - 68px));
    max-height:900px;
    display:grid;
    grid-template-columns:
        minmax(0,1.12fr)
        minmax(440px,.88fr);
    overflow:hidden;
    border:1px solid rgba(200,210,225,.85);
    border-radius:28px;
    background:#fff;
    box-shadow:var(--erp-shadow);
}

/* =========================================================
   LEFT SCHOOL ERP VISUAL AREA
   ========================================================= */

.erp-visual-panel{
    position:relative;
    min-width:0;
    min-height:0;
    overflow:hidden;
    background:
        linear-gradient(
            145deg,
            #eef0ff 0%,
            #f0f4ff 42%,
            #edf8ff 100%
        );
}

.erp-visual-panel::before{
    content:"";
    position:absolute;
    inset:0;
    pointer-events:none;
    background:
        radial-gradient(
            circle at 15% 15%,
            rgba(94,49,236,.12),
            transparent 28%
        ),
        radial-gradient(
            circle at 84% 77%,
            rgba(12,193,207,.13),
            transparent 31%
        );
}

.erp-visual-panel::after{
    content:"";
    position:absolute;
    inset:auto -8% -23% 20%;
    height:44%;
    pointer-events:none;
    border-radius:50%;
    background:rgba(91,73,235,.055);
    filter:blur(4px);
}

.erp-visual-inner{
    position:relative;
    z-index:2;
    height:100%;
    min-height:100%;
    display:grid;
    grid-template-rows:auto auto minmax(0,1fr) auto;
    gap:12px;
    padding:
        clamp(28px,4.4vw,58px)
        clamp(28px,4.2vw,62px)
        clamp(24px,3.3vw,44px);
}

.erp-brand{
    display:flex;
    align-items:center;
    gap:15px;
    min-width:0;
}

.erp-brand-logo{
    display:block;
    width:clamp(260px,29vw,430px);
    max-width:72%;
    height:auto;
    mix-blend-mode:multiply;
}

.erp-brand-divider{
    width:1px;
    height:36px;
    background:rgba(32,59,108,.16);
}

.erp-brand-mini{
    min-width:0;
}

.erp-brand-mini strong{
    display:block;
    color:var(--erp-navy);
    font-size:12px;
    font-weight:900;
}

.erp-brand-mini span{
    display:block;
    margin-top:3px;
    color:#77849c;
    font-size:9px;
    font-weight:760;
}

.erp-hero-copy{
    max-width:610px;
    padding-top:8px;
}

.erp-hero-copy h1{
    margin:0;
    color:var(--erp-navy);
    font-size:
        clamp(
            31px,
            3.1vw,
            48px
        );
    line-height:1.05;
    letter-spacing:-.035em;
    font-weight:900;
}

.erp-hero-copy p{
    max-width:560px;
    margin:13px 0 0;
    color:#65738c;
    font-size:
        clamp(
            13px,
            1.12vw,
            17px
        );
    line-height:1.6;
    font-weight:540;
}

.erp-slideshow{
    position:relative;
    min-height:390px;
    margin-top:4px;
    overflow:hidden;
    border-radius:24px;
}

.erp-slide{
    position:absolute;
    inset:0;
    display:grid;
    grid-template-columns:
        minmax(0,1fr)
        minmax(260px,.95fr);
    align-items:center;
    gap:22px;
    opacity:0;
    visibility:hidden;
    transform:
        translateX(36px)
        scale(.985);
    transition:
        opacity .75s ease,
        transform .75s
        cubic-bezier(.16,1,.3,1),
        visibility .75s ease;
}

.erp-slide.is-active{
    opacity:1;
    visibility:visible;
    transform:none;
}

.erp-slide-copy{
    position:relative;
    z-index:3;
    align-self:center;
    max-width:340px;
}

.erp-slide-kicker{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 10px;
    border:1px solid rgba(68,86,209,.12);
    border-radius:999px;
    background:rgba(255,255,255,.72);
    color:#4d4ed4;
    font-size:9px;
    font-weight:900;
    letter-spacing:.03em;
    box-shadow:0 8px 22px rgba(47,71,143,.06);
    backdrop-filter:blur(8px);
}

.erp-slide-kicker svg{
    width:13px;
    height:13px;
}

.erp-slide h2{
    margin:14px 0 0;
    color:#1a2d5d;
    font-size:
        clamp(
            23px,
            2.15vw,
            34px
        );
    line-height:1.12;
    letter-spacing:-.025em;
    font-weight:900;
}

.erp-slide p{
    margin:10px 0 0;
    color:#687690;
    font-size:
        clamp(
            11px,
            .9vw,
            14px
        );
    line-height:1.55;
}

.erp-slide-points{
    display:grid;
    gap:7px;
    margin-top:16px;
}

.erp-slide-point{
    display:flex;
    align-items:center;
    gap:8px;
    color:#4f5f79;
    font-size:10px;
    font-weight:750;
}

.erp-slide-point::before{
    content:"";
    width:7px;
    height:7px;
    flex:0 0 auto;
    border-radius:50%;
    background:
        linear-gradient(
            135deg,
            var(--erp-violet),
            var(--erp-cyan)
        );
    box-shadow:0 0 0 4px rgba(92,71,225,.08);
}

.erp-slide-art{
    position:relative;
    min-width:0;
    width:100%;
    height:100%;
    display:grid;
    place-items:center;
}

.erp-slide-art::before{
    content:"";
    position:absolute;
    width:88%;
    aspect-ratio:1;
    border-radius:50%;
    background:
        radial-gradient(
            circle,
            rgba(255,255,255,.88),
            rgba(255,255,255,.28) 56%,
            transparent 72%
        );
}

.erp-school-art{
    position:relative;
    z-index:2;
    display:block;
    width:min(100%,480px);
    height:auto;
    max-height:380px;
    filter:
        drop-shadow(
            0 22px 26px
            rgba(48,73,125,.13)
        );
    animation:
        erpArtFloat
        5.5s
        ease-in-out
        infinite;
}

.erp-slide:nth-child(2)
.erp-school-art{
    animation-delay:-1.5s;
}

.erp-slide:nth-child(3)
.erp-school-art{
    animation-delay:-2.6s;
}

.erp-slide:nth-child(4)
.erp-school-art{
    animation-delay:-3.8s;
}

@keyframes erpArtFloat{
    0%,100%{
        transform:translateY(0);
    }
    50%{
        transform:translateY(-10px);
    }
}

.erp-slider-foot{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    margin-top:2px;
}

.erp-slide-dots{
    display:flex;
    align-items:center;
    gap:8px;
}

.erp-slide-dot{
    width:8px;
    height:8px;
    padding:0;
    border:0;
    border-radius:999px;
    background:#bdc8dc;
    cursor:pointer;
    transition:
        width .25s ease,
        background .25s ease,
        transform .25s ease;
}

.erp-slide-dot:hover{
    transform:scale(1.12);
}

.erp-slide-dot.is-active{
    width:30px;
    background:
        linear-gradient(
            90deg,
            var(--erp-violet),
            var(--erp-blue),
            var(--erp-cyan)
        );
}

.erp-trust-line{
    display:flex;
    align-items:center;
    gap:9px;
    color:#687690;
    font-size:9px;
    font-weight:780;
    white-space:nowrap;
}

.erp-trust-line svg{
    width:15px;
    height:15px;
    color:#3772df;
}

/* =========================================================
   RIGHT LOGIN PANEL
   ========================================================= */

.erp-auth-panel{
    position:relative;
    min-width:0;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:
        clamp(30px,4.3vw,68px)
        clamp(28px,4.4vw,70px);
    background:
        linear-gradient(
            180deg,
            rgba(255,255,255,1),
            rgba(252,253,255,1)
        );
}

.erp-auth-panel::before{
    content:"";
    position:absolute;
    top:8%;
    right:-20%;
    width:380px;
    height:380px;
    border-radius:50%;
    background:
        radial-gradient(
            circle,
            rgba(20,201,211,.08),
            transparent 68%
        );
    pointer-events:none;
}

.erp-auth-card{
    position:relative;
    z-index:2;
    width:min(470px,100%);
    padding:
        clamp(28px,3vw,42px);
    border:1px solid rgba(221,228,239,.88);
    border-radius:24px;
    background:rgba(255,255,255,.9);
    box-shadow:
        0 18px 50px rgba(24,48,88,.09),
        inset 0 1px 0 rgba(255,255,255,.95);
    backdrop-filter:blur(12px);
}

.erp-mobile-brand{
    display:none;
    margin-bottom:20px;
    text-align:center;
}

.erp-mobile-brand img{
    width:190px;
    max-width:72%;
    height:auto;
    mix-blend-mode:multiply;
}

.erp-auth-title{
    margin:0;
    color:var(--erp-navy);
    font-size:
        clamp(
            29px,
            2.4vw,
            38px
        );
    line-height:1.08;
    letter-spacing:-.035em;
    text-align:center;
    font-weight:900;
}

.erp-auth-subtitle{
    margin:10px 0 28px;
    color:#7b879b;
    font-size:12px;
    line-height:1.55;
    text-align:center;
}

.erp-login-error{
    margin:0 0 18px;
    padding:12px 13px;
    border:1px solid #fecaca;
    border-radius:12px;
    background:#fff7f7;
    color:#b42318;
    font-size:11px;
    line-height:1.45;
    font-weight:740;
}

.erp-field{
    margin-bottom:17px;
}

.erp-field-label-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:7px;
}

.erp-field label{
    margin:0;
    color:#24365d;
    font-size:11px;
    font-weight:850;
}

.erp-input-wrap{
    position:relative;
}

.erp-input-wrap .form-control{
    width:100%;
    min-height:52px;
    padding:
        12px
        46px
        12px
        44px;
    border:1px solid #dce4ee;
    border-radius:11px;
    background:#fff;
    color:#17223d;
    font-size:12px;
    box-shadow:none;
    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}

.erp-input-wrap .form-control::placeholder{
    color:#9ba5b6;
}

.erp-input-wrap .form-control:focus{
    border-color:#86aef3;
    background:#fff;
    box-shadow:
        0 0 0 4px
        rgba(47,103,223,.08);
}

.erp-input-icon{
    position:absolute;
    top:50%;
    left:14px;
    width:18px;
    height:18px;
    transform:translateY(-50%);
    color:#8e9bac;
    pointer-events:none;
}

.erp-input-icon svg,
.erp-password-toggle svg{
    width:100%;
    height:100%;
}

.erp-password-toggle{
    position:absolute;
    top:50%;
    right:9px;
    width:35px;
    height:35px;
    display:grid;
    place-items:center;
    padding:0;
    border:0;
    border-radius:9px;
    transform:translateY(-50%);
    background:transparent;
    color:#8e9bac;
    cursor:pointer;
    transition:
        background .18s ease,
        color .18s ease;
}

.erp-password-toggle:hover{
    background:#f2f5fa;
    color:#42516c;
}

.erp-helper-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin:3px 0 18px;
    color:#7a879b;
    font-size:9px;
}

.erp-helper-row span{
    display:flex;
    align-items:center;
    gap:6px;
}

.erp-helper-row svg{
    width:13px;
    height:13px;
    color:#4b70cf;
}

.erp-login-submit{
    width:100%;
    min-height:54px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    margin-top:2px;
    padding:12px 18px;
    border:0;
    border-radius:12px;
    color:#fff;
    background:
        linear-gradient(
            100deg,
            var(--erp-violet),
            #3e63ed 50%,
            var(--erp-cyan)
        );
    background-size:180% 100%;
    box-shadow:
        0 14px 28px
        rgba(65,88,225,.21);
    font-size:12px;
    font-weight:900;
    cursor:pointer;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        background-position .35s ease;
}

.erp-login-submit:hover{
    transform:translateY(-1px);
    background-position:100% 0;
    box-shadow:
        0 18px 34px
        rgba(65,88,225,.25);
}

.erp-login-submit svg{
    width:17px;
    height:17px;
}

.erp-login-submit:active{
    transform:none;
}

.erp-security-note{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    margin-top:19px;
    color:#8490a2;
    font-size:9px;
    font-weight:760;
}

.erp-security-note svg{
    width:14px;
    height:14px;
    color:#3971df;
}

.erp-auth-divider{
    display:flex;
    align-items:center;
    gap:10px;
    margin:24px 0 18px;
    color:#a1aaba;
    font-size:8px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.erp-auth-divider::before,
.erp-auth-divider::after{
    content:"";
    height:1px;
    flex:1;
    background:#edf0f5;
}

.erp-module-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:8px;
}

.erp-module-chip{
    min-width:0;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:8px 7px;
    border:1px solid #e8edf4;
    border-radius:10px;
    background:#fbfcfe;
    color:#68758c;
    font-size:8px;
    font-weight:820;
    white-space:nowrap;
}

.erp-module-chip svg{
    width:13px;
    height:13px;
    flex:0 0 auto;
    color:#3c6edb;
}

/* =========================================================
   FOOTER
   ========================================================= */

.erp-login-footer{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:13px 8px 0;
    color:#7b8799;
    font-size:10px;
    text-align:center;
}

.erp-login-footer strong{
    color:#4f5f78;
}

/* =========================================================
   RESPONSIVE - LARGE DESKTOPS
   ========================================================= */

@media(min-width:1700px){
    .erp-login-wrap{
        width:min(1540px,100%);
    }

    .erp-shell{
        min-height:min(850px,calc(100dvh - 72px));
        grid-template-columns:
            minmax(0,1.16fr)
            minmax(470px,.84fr);
    }

    .erp-visual-inner{
        padding-left:68px;
        padding-right:68px;
    }

    .erp-auth-card{
        width:min(490px,100%);
    }
}

/* =========================================================
   RESPONSIVE - 1280 / 1366 / 1440 LAPTOPS
   ========================================================= */

@media(max-width:1366px){
    .erp-login-stage{
        padding:18px 20px 14px;
    }

    .erp-shell{
        min-height:min(760px,calc(100dvh - 46px));
        border-radius:24px;
        grid-template-columns:
            minmax(0,1.08fr)
            minmax(410px,.92fr);
    }

    .erp-visual-inner{
        padding:34px 38px 28px;
    }

    .erp-brand-logo{
        width:320px;
    }

    .erp-slideshow{
        min-height:340px;
    }

    .erp-auth-panel{
        padding:32px 34px;
    }

    .erp-auth-card{
        padding:30px;
        border-radius:21px;
    }
}

@media(max-width:1180px){
    .erp-shell{
        grid-template-columns:
            minmax(0,1fr)
            minmax(390px,.95fr);
    }

    .erp-brand-mini,
    .erp-brand-divider{
        display:none;
    }

    .erp-brand-logo{
        max-width:82%;
    }

    .erp-slide{
        grid-template-columns:
            minmax(0,.93fr)
            minmax(220px,1.07fr);
        gap:10px;
    }

    .erp-slide-points{
        display:none;
    }

    .erp-auth-panel{
        padding:28px;
    }
}

/* =========================================================
   RESPONSIVE - TABLETS
   ========================================================= */

@media(max-width:940px){
    body.erp-login-page{
        overflow:auto;
    }

    .erp-login-stage{
        align-items:flex-start;
        min-height:100dvh;
        padding:
            max(16px,env(safe-area-inset-top))
            16px
            max(16px,env(safe-area-inset-bottom));
    }

    .erp-shell{
        min-height:0;
        max-height:none;
        grid-template-columns:1fr;
        border-radius:24px;
    }

    .erp-visual-panel{
        min-height:360px;
    }

    .erp-visual-inner{
        min-height:360px;
        grid-template-rows:auto auto 1fr auto;
        padding:28px 30px 22px;
    }

    .erp-brand-logo{
        width:280px;
        max-width:62%;
    }

    .erp-hero-copy{
        display:none;
    }

    .erp-slideshow{
        min-height:235px;
    }

    .erp-slide{
        grid-template-columns:
            minmax(0,.85fr)
            minmax(250px,1.15fr);
    }

    .erp-slide h2{
        font-size:25px;
    }

    .erp-slide p{
        font-size:11px;
    }

    .erp-school-art{
        max-height:230px;
    }

    .erp-auth-panel{
        padding:34px 28px 42px;
    }

    .erp-auth-card{
        width:min(520px,100%);
    }
}

/* =========================================================
   RESPONSIVE - MOBILE
   ========================================================= */

@media(max-width:640px){
    .erp-login-stage{
        padding:0;
    }

    .erp-login-wrap{
        width:100%;
    }

    .erp-shell{
        border:0;
        border-radius:0;
        box-shadow:none;
    }

    .erp-visual-panel{
        min-height:255px;
    }

    .erp-visual-inner{
        min-height:255px;
        padding:
            max(18px,env(safe-area-inset-top))
            18px
            16px;
    }

    .erp-brand{
        justify-content:center;
    }

    .erp-brand-logo{
        width:220px;
        max-width:72vw;
    }

    .erp-slideshow{
        min-height:160px;
        margin-top:0;
    }

    .erp-slide{
        grid-template-columns:
            minmax(0,.9fr)
            minmax(150px,1.1fr);
        gap:4px;
    }

    .erp-slide-copy{
        max-width:180px;
    }

    .erp-slide-kicker{
        padding:5px 7px;
        font-size:7px;
    }

    .erp-slide h2{
        margin-top:8px;
        font-size:17px;
        line-height:1.1;
    }

    .erp-slide p{
        display:none;
    }

    .erp-school-art{
        max-height:155px;
    }

    .erp-slider-foot{
        margin-top:0;
    }

    .erp-trust-line{
        display:none;
    }

    .erp-auth-panel{
        padding:
            26px
            14px
            max(26px,env(safe-area-inset-bottom));
    }

    .erp-auth-card{
        width:100%;
        padding:24px 18px;
        border-radius:20px;
    }

    .erp-auth-title{
        font-size:28px;
    }

    .erp-auth-subtitle{
        margin-bottom:23px;
        font-size:11px;
    }

    .erp-module-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:6px;
    }

    .erp-module-chip{
        padding:7px 4px;
        font-size:7px;
    }

    .erp-login-footer{
        display:none;
    }
}

@media(max-width:420px){
    .erp-visual-panel{
        min-height:220px;
    }

    .erp-visual-inner{
        min-height:220px;
        padding-left:14px;
        padding-right:14px;
    }

    .erp-brand-logo{
        width:190px;
    }

    .erp-slideshow{
        min-height:135px;
    }

    .erp-slide{
        grid-template-columns:
            minmax(0,.86fr)
            minmax(128px,1.14fr);
    }

    .erp-slide h2{
        font-size:15px;
    }

    .erp-slide-kicker{
        font-size:6.5px;
    }

    .erp-school-art{
        max-height:132px;
    }

    .erp-auth-panel{
        padding-left:10px;
        padding-right:10px;
    }

    .erp-auth-card{
        padding:22px 15px;
    }

    .erp-module-chip{
        font-size:6.6px;
    }
}

/* =========================================================
   RESPONSIVE - SHORT DESKTOP HEIGHTS
   ========================================================= */

@media(max-height:760px) and (min-width:941px){
    .erp-login-stage{
        padding-top:14px;
        padding-bottom:10px;
    }

    .erp-shell{
        min-height:calc(100dvh - 34px);
    }

    .erp-visual-inner{
        padding-top:26px;
        padding-bottom:22px;
    }

    .erp-brand-logo{
        width:290px;
    }

    .erp-hero-copy{
        padding-top:0;
    }

    .erp-hero-copy h1{
        font-size:34px;
    }

    .erp-hero-copy p{
        margin-top:8px;
        font-size:12px;
    }

    .erp-slideshow{
        min-height:300px;
    }

    .erp-school-art{
        max-height:280px;
    }

    .erp-auth-panel{
        padding-top:20px;
        padding-bottom:20px;
    }

    .erp-auth-card{
        padding:24px 28px;
    }

    .erp-auth-subtitle{
        margin-bottom:18px;
    }

    .erp-field{
        margin-bottom:12px;
    }

    .erp-input-wrap .form-control{
        min-height:47px;
    }

    .erp-login-submit{
        min-height:48px;
    }

    .erp-auth-divider{
        margin:17px 0 13px;
    }

    .erp-security-note{
        margin-top:14px;
    }

    .erp-login-footer{
        display:none;
    }
}

@media(prefers-reduced-motion:reduce){
    *,
    *::before,
    *::after{
        animation-duration:.01ms !important;
        animation-iteration-count:1 !important;
        scroll-behavior:auto !important;
        transition-duration:.01ms !important;
    }
}
</style>
</head>

<body
    class="erp-login-page <?= $error !== '' ? 'erp-login-ready has-login-error' : 'erp-intro-active' ?>"
    data-login-error="<?= $error !== '' ? '1' : '0' ?>"
>

<div
    id="erpIntro"
    class="erp-intro"
    <?= $error !== '' ? 'hidden' : '' ?>
>
    <div class="erp-intro-inner">
        <img
            class="erp-intro-logo"
            src="assets/images/ecommer-logo.jpg"
            alt="ECOMMER Cloud Based Smart Billing Software"
        >

        <div class="erp-intro-caption">
            School ERP · Smart Campus Management
        </div>

        <div class="erp-intro-progress">
            <span></span>
        </div>
    </div>
</div>

<main class="erp-login-stage">
    <div class="erp-login-wrap">

        <section class="erp-shell">

            <section
                class="erp-visual-panel"
                aria-label="School ERP highlights"
            >
                <div class="erp-visual-inner">

                    <div class="erp-brand">
                        <img
                            class="erp-brand-logo"
                            src="assets/images/ecommer-logo.jpg"
                            alt="ECOMMER"
                        >

                        <span class="erp-brand-divider"></span>

                        <div class="erp-brand-mini">
                            <strong>School ERP</strong>
                            <span>Cloud Based Smart School Management</span>
                        </div>
                    </div>

                    <div class="erp-hero-copy">
                        <h1>One smart platform for your entire school.</h1>
                        <p>
                            Manage students, academics, fees, attendance,
                            staff, transport and reports from one secure ERP.
                        </p>
                    </div>

                    <div
                        id="schoolErpSlideshow"
                        class="erp-slideshow"
                    >

                        <!-- SLIDE 1: STUDENTS & SCHOOL -->
                        <article
                            class="erp-slide is-active"
                            data-slide="0"
                        >
                            <div class="erp-slide-copy">
                                <span class="erp-slide-kicker">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    Student Management
                                </span>

                                <h2>
                                    Admissions to graduation,
                                    all in one place.
                                </h2>

                                <p>
                                    Keep student profiles, parent details,
                                    class allocation and academic history
                                    organized and easy to access.
                                </p>

                                <div class="erp-slide-points">
                                    <span class="erp-slide-point">
                                        Student admission & profiles
                                    </span>
                                    <span class="erp-slide-point">
                                        Class & section management
                                    </span>
                                    <span class="erp-slide-point">
                                        Parent / guardian records
                                    </span>
                                </div>
                            </div>

                            <div class="erp-slide-art">
                                <svg
                                    class="erp-school-art"
                                    viewBox="0 0 560 430"
                                    role="img"
                                    aria-label="Students walking to school"
                                >
                                    <defs>
                                        <linearGradient id="schoolWall" x1="0" x2="1">
                                            <stop offset="0" stop-color="#FFD267"/>
                                            <stop offset="1" stop-color="#FFB94B"/>
                                        </linearGradient>
                                        <linearGradient id="schoolRoof" x1="0" x2="1">
                                            <stop offset="0" stop-color="#2B65C7"/>
                                            <stop offset="1" stop-color="#184A9E"/>
                                        </linearGradient>
                                        <linearGradient id="bagBlue" x1="0" x2="1">
                                            <stop offset="0" stop-color="#4B8FE8"/>
                                            <stop offset="1" stop-color="#2A67C7"/>
                                        </linearGradient>
                                        <linearGradient id="bagPink" x1="0" x2="1">
                                            <stop offset="0" stop-color="#FF7FB2"/>
                                            <stop offset="1" stop-color="#E84B8A"/>
                                        </linearGradient>
                                    </defs>

                                    <ellipse
                                        cx="286"
                                        cy="397"
                                        rx="220"
                                        ry="22"
                                        fill="#CCD9F2"
                                        opacity=".45"
                                    />

                                    <!-- trees -->
                                    <g opacity=".95">
                                        <circle cx="76" cy="263" r="34" fill="#85D58C"/>
                                        <rect x="70" y="264" width="12" height="73" rx="6" fill="#74B76E"/>
                                        <circle cx="486" cy="258" r="38" fill="#79CF87"/>
                                        <rect x="480" y="263" width="12" height="78" rx="6" fill="#69B16C"/>
                                        <circle cx="117" cy="283" r="29" fill="#9AE29A"/>
                                        <circle cx="447" cy="287" r="27" fill="#9ADE9B"/>
                                    </g>

                                    <!-- school -->
                                    <g>
                                        <rect
                                            x="133"
                                            y="159"
                                            width="296"
                                            height="183"
                                            rx="8"
                                            fill="url(#schoolWall)"
                                        />
                                        <polygon
                                            points="114,176 280,89 447,176"
                                            fill="url(#schoolRoof)"
                                        />
                                        <polygon
                                            points="177,166 280,112 384,166"
                                            fill="#F7B53E"
                                        />

                                        <rect
                                            x="247"
                                            y="207"
                                            width="66"
                                            height="135"
                                            rx="33"
                                            fill="#2D65B8"
                                        />
                                        <rect
                                            x="261"
                                            y="220"
                                            width="39"
                                            height="122"
                                            rx="18"
                                            fill="#3F78D0"
                                        />

                                        <circle
                                            cx="280"
                                            cy="153"
                                            r="29"
                                            fill="#fff"
                                            stroke="#E3EAF5"
                                            stroke-width="5"
                                        />
                                        <line
                                            x1="280"
                                            y1="153"
                                            x2="280"
                                            y2="135"
                                            stroke="#4771B6"
                                            stroke-width="4"
                                            stroke-linecap="round"
                                        />
                                        <line
                                            x1="280"
                                            y1="153"
                                            x2="294"
                                            y2="160"
                                            stroke="#4771B6"
                                            stroke-width="4"
                                            stroke-linecap="round"
                                        />

                                        <rect
                                            x="219"
                                            y="181"
                                            width="123"
                                            height="31"
                                            rx="7"
                                            fill="#fff"
                                            opacity=".96"
                                        />
                                        <text
                                            x="280"
                                            y="202"
                                            text-anchor="middle"
                                            font-size="18"
                                            font-weight="800"
                                            fill="#254E91"
                                            font-family="Arial, sans-serif"
                                        >SCHOOL</text>

                                        <!-- windows -->
                                        <g fill="#EAF7FF" stroke="#6EA2D7" stroke-width="4">
                                            <rect x="158" y="225" width="55" height="48" rx="4"/>
                                            <rect x="348" y="225" width="55" height="48" rx="4"/>
                                            <rect x="158" y="286" width="55" height="42" rx="4"/>
                                            <rect x="348" y="286" width="55" height="42" rx="4"/>
                                        </g>

                                        <!-- flag -->
                                        <line
                                            x1="280"
                                            y1="89"
                                            x2="280"
                                            y2="51"
                                            stroke="#315EA8"
                                            stroke-width="5"
                                        />
                                        <path
                                            d="M281 52 C306 47,316 61,338 54 L338 78 C316 85,305 71,281 77Z"
                                            fill="#3C80DB"
                                        />
                                    </g>

                                    <!-- students -->
                                    <g transform="translate(62 249)">
                                        <circle cx="55" cy="34" r="22" fill="#F1B68E"/>
                                        <path d="M35 27c6-23 40-25 45 1-10-4-21-9-45-1z" fill="#233A69"/>
                                        <rect x="37" y="55" width="38" height="73" rx="17" fill="#F8F9FC"/>
                                        <rect x="34" y="69" width="44" height="52" rx="14" fill="url(#bagBlue)"/>
                                        <rect x="42" y="117" width="13" height="63" rx="6" fill="#1F3F7C"/>
                                        <rect x="61" y="117" width="13" height="63" rx="6" fill="#1F3F7C"/>
                                    </g>

                                    <g transform="translate(175 260)">
                                        <circle cx="53" cy="32" r="21" fill="#E9AD85"/>
                                        <path d="M33 31c1-25 39-31 43 1-9-9-30-11-43-1z" fill="#4C2E35"/>
                                        <path d="M37 54h32l13 77H24z" fill="#F5F4F7"/>
                                        <path d="M31 64h44l6 63H27z" fill="#F3A92E"/>
                                        <rect x="37" y="127" width="12" height="52" rx="6" fill="#243A71"/>
                                        <rect x="58" y="127" width="12" height="52" rx="6" fill="#243A71"/>
                                    </g>

                                    <g transform="translate(305 254)">
                                        <circle cx="52" cy="34" r="22" fill="#F2BC92"/>
                                        <path d="M31 30c7-25 43-25 46 3-13-8-28-10-46-3z" fill="#5A3B2D"/>
                                        <rect x="34" y="56" width="38" height="73" rx="17" fill="#F9FAFC"/>
                                        <rect x="31" y="69" width="44" height="52" rx="14" fill="#31B792"/>
                                        <rect x="38" y="124" width="13" height="56" rx="6" fill="#23457B"/>
                                        <rect x="58" y="124" width="13" height="56" rx="6" fill="#23457B"/>
                                    </g>

                                    <g transform="translate(414 260)">
                                        <circle cx="48" cy="32" r="21" fill="#E8A979"/>
                                        <path d="M25 34c-2-28 46-31 48-1l-5 24H31z" fill="#52303B"/>
                                        <path d="M32 54h32l13 77H19z" fill="#F8F7FA"/>
                                        <path d="M25 65h45l5 62H20z" fill="url(#bagPink)"/>
                                        <rect x="32" y="127" width="12" height="52" rx="6" fill="#263D72"/>
                                        <rect x="53" y="127" width="12" height="52" rx="6" fill="#263D72"/>
                                    </g>
                                </svg>
                            </div>
                        </article>

                        <!-- SLIDE 2: ACADEMICS -->
                        <article
                            class="erp-slide"
                            data-slide="1"
                        >
                            <div class="erp-slide-copy">
                                <span class="erp-slide-kicker">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M2 10 12 5l10 5-10 5z"/>
                                        <path d="M6 12v5c3 2 9 2 12 0v-5"/>
                                    </svg>
                                    Academics & Attendance
                                </span>

                                <h2>
                                    Plan classes and track every school day.
                                </h2>

                                <p>
                                    Organize academic years, subjects,
                                    timetables, sections and attendance with
                                    simple real-time workflows.
                                </p>

                                <div class="erp-slide-points">
                                    <span class="erp-slide-point">
                                        Class-wise timetable
                                    </span>
                                    <span class="erp-slide-point">
                                        Subject & book assignment
                                    </span>
                                    <span class="erp-slide-point">
                                        Student attendance
                                    </span>
                                </div>
                            </div>

                            <div class="erp-slide-art">
                                <svg
                                    class="erp-school-art"
                                    viewBox="0 0 560 430"
                                    role="img"
                                    aria-label="Academic planning dashboard illustration"
                                >
                                    <defs>
                                        <linearGradient id="boardGrad" x1="0" x2="1">
                                            <stop offset="0" stop-color="#4669E9"/>
                                            <stop offset="1" stop-color="#18BFD2"/>
                                        </linearGradient>
                                    </defs>

                                    <ellipse
                                        cx="284"
                                        cy="385"
                                        rx="210"
                                        ry="22"
                                        fill="#CDD8EE"
                                        opacity=".4"
                                    />

                                    <rect
                                        x="87"
                                        y="72"
                                        width="384"
                                        height="268"
                                        rx="26"
                                        fill="#fff"
                                        stroke="#DDE7F4"
                                        stroke-width="4"
                                    />

                                    <rect
                                        x="108"
                                        y="95"
                                        width="342"
                                        height="58"
                                        rx="16"
                                        fill="url(#boardGrad)"
                                    />

                                    <circle cx="136" cy="124" r="16" fill="#fff" opacity=".24"/>
                                    <path
                                        d="M129 124h14M136 117v14"
                                        stroke="#fff"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                    />

                                    <text
                                        x="169"
                                        y="130"
                                        fill="#fff"
                                        font-size="18"
                                        font-weight="800"
                                        font-family="Arial, sans-serif"
                                    >Academic Overview</text>

                                    <g>
                                        <rect x="111" y="175" width="95" height="72" rx="14" fill="#F3F0FF"/>
                                        <rect x="227" y="175" width="95" height="72" rx="14" fill="#EAF7FF"/>
                                        <rect x="343" y="175" width="95" height="72" rx="14" fill="#ECFBF4"/>

                                        <circle cx="141" cy="202" r="13" fill="#6B4CF1"/>
                                        <circle cx="257" cy="202" r="13" fill="#3F82E5"/>
                                        <circle cx="373" cy="202" r="13" fill="#38B98B"/>

                                        <rect x="130" y="223" width="54" height="8" rx="4" fill="#C7BDF8"/>
                                        <rect x="246" y="223" width="54" height="8" rx="4" fill="#B7D9F7"/>
                                        <rect x="362" y="223" width="54" height="8" rx="4" fill="#BDEBD5"/>
                                    </g>

                                    <g>
                                        <rect x="111" y="270" width="327" height="48" rx="12" fill="#F7F9FC"/>
                                        <rect x="128" y="286" width="88" height="9" rx="4.5" fill="#BFCADF"/>
                                        <rect x="235" y="286" width="55" height="9" rx="4.5" fill="#D5DDEA"/>
                                        <rect x="310" y="286" width="111" height="9" rx="4.5" fill="#C7D2E6"/>
                                        <circle cx="411" cy="294" r="10" fill="#42B983"/>
                                    </g>

                                    <g transform="translate(58 217)">
                                        <circle cx="48" cy="38" r="24" fill="#E9AE83"/>
                                        <path d="M24 36c4-29 44-34 50-2-17-11-32-10-50 2z" fill="#31406D"/>
                                        <path d="M20 70c4-25 52-25 57 0l7 82H13z" fill="#526EE8"/>
                                        <rect x="38" y="64" width="18" height="53" rx="7" fill="#fff"/>
                                        <path d="M44 118l-13 73M53 118l16 73" stroke="#253E73" stroke-width="13" stroke-linecap="round"/>
                                    </g>

                                    <g transform="translate(435 240)">
                                        <circle cx="38" cy="30" r="20" fill="#F1B48A"/>
                                        <path d="M18 28c5-23 37-25 41 2-13-7-26-7-41-2z" fill="#5B3B33"/>
                                        <path d="M12 58h51l10 78H3z" fill="#F5A737"/>
                                    </g>
                                </svg>
                            </div>
                        </article>

                        <!-- SLIDE 3: FEES -->
                        <article
                            class="erp-slide"
                            data-slide="2"
                        >
                            <div class="erp-slide-copy">
                                <span class="erp-slide-kicker">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M6 2h9l5 5v15H6z"/>
                                        <path d="M14 2v6h6"/>
                                        <path d="M9 13h7M9 17h5"/>
                                    </svg>
                                    Fees & Accounts
                                </span>

                                <h2>
                                    Simple fee collection with clear records.
                                </h2>

                                <p>
                                    Configure fee structures, collect payments,
                                    print receipts and review school accounts
                                    with complete student-wise visibility.
                                </p>

                                <div class="erp-slide-points">
                                    <span class="erp-slide-point">
                                        Fee structures & collections
                                    </span>
                                    <span class="erp-slide-point">
                                        Receipts & payment history
                                    </span>
                                    <span class="erp-slide-point">
                                        Income & expense tracking
                                    </span>
                                </div>
                            </div>

                            <div class="erp-slide-art">
                                <svg
                                    class="erp-school-art"
                                    viewBox="0 0 560 430"
                                    role="img"
                                    aria-label="School fees and payments illustration"
                                >
                                    <defs>
                                        <linearGradient id="feeCard" x1="0" x2="1">
                                            <stop offset="0" stop-color="#6A38F0"/>
                                            <stop offset="1" stop-color="#2F7CE2"/>
                                        </linearGradient>
                                    </defs>

                                    <ellipse
                                        cx="280"
                                        cy="386"
                                        rx="205"
                                        ry="23"
                                        fill="#CBD8EE"
                                        opacity=".42"
                                    />

                                    <rect
                                        x="102"
                                        y="88"
                                        width="350"
                                        height="244"
                                        rx="28"
                                        fill="#fff"
                                        stroke="#DEE7F3"
                                        stroke-width="4"
                                    />

                                    <rect
                                        x="126"
                                        y="111"
                                        width="302"
                                        height="91"
                                        rx="18"
                                        fill="url(#feeCard)"
                                    />

                                    <text
                                        x="150"
                                        y="143"
                                        fill="#DDE9FF"
                                        font-size="12"
                                        font-family="Arial, sans-serif"
                                    >FEES COLLECTED</text>

                                    <text
                                        x="150"
                                        y="177"
                                        fill="#fff"
                                        font-size="29"
                                        font-weight="900"
                                        font-family="Arial, sans-serif"
                                    >₹ 1,84,500</text>

                                    <circle cx="382" cy="157" r="24" fill="#fff" opacity=".18"/>
                                    <path
                                        d="M382 143v28M372 150h15c8 0 8 11 0 11h-10c-8 0-8 11 0 11h15"
                                        fill="none"
                                        stroke="#fff"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                    />

                                    <g>
                                        <rect x="126" y="224" width="302" height="82" rx="16" fill="#F7F9FC"/>
                                        <rect x="147" y="243" width="75" height="9" rx="4.5" fill="#BFCADF"/>
                                        <rect x="147" y="264" width="145" height="8" rx="4" fill="#D3DBE8"/>
                                        <rect x="147" y="283" width="105" height="8" rx="4" fill="#E0E5ED"/>
                                        <rect x="329" y="242" width="74" height="42" rx="10" fill="#E8FAF1"/>
                                        <path d="m348 263 9 9 27-29" fill="none" stroke="#31B878" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </g>

                                    <g transform="translate(54 236)">
                                        <circle cx="50" cy="34" r="22" fill="#EDB187"/>
                                        <path d="M29 31c6-24 41-28 45 2-13-8-29-10-45-2z" fill="#273D68"/>
                                        <path d="M22 63h55l8 78H13z" fill="#34B99A"/>
                                        <rect x="36" y="65" width="28" height="42" rx="8" fill="#fff" opacity=".9"/>
                                    </g>

                                    <g transform="translate(431 226)">
                                        <rect x="2" y="19" width="74" height="112" rx="16" fill="#6F4CF0"/>
                                        <rect x="11" y="29" width="56" height="82" rx="8" fill="#F7F8FF"/>
                                        <circle cx="39" cy="121" r="5" fill="#D7D1FB"/>
                                        <rect x="20" y="45" width="38" height="8" rx="4" fill="#B8C9E9"/>
                                        <rect x="20" y="62" width="30" height="8" rx="4" fill="#D4DDEC"/>
                                        <rect x="20" y="83" width="38" height="14" rx="7" fill="#30B991"/>
                                    </g>
                                </svg>
                            </div>
                        </article>

                        <!-- SLIDE 4: TRANSPORT & SECURITY -->
                        <article
                            class="erp-slide"
                            data-slide="3"
                        >
                            <div class="erp-slide-copy">
                                <span class="erp-slide-kicker">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <rect x="3" y="6" width="18" height="11" rx="3"/>
                                        <path d="M7 17v2M17 17v2M3 11h18"/>
                                        <circle cx="7.5" cy="14" r="1"/>
                                        <circle cx="16.5" cy="14" r="1"/>
                                    </svg>
                                    Transport & Security
                                </span>

                                <h2>
                                    Safer transport and role-based school access.
                                </h2>

                                <p>
                                    Manage buses, routes and stops while
                                    ensuring every staff member sees only the
                                    modules and actions they are allowed to use.
                                </p>

                                <div class="erp-slide-points">
                                    <span class="erp-slide-point">
                                        Bus, route & stop management
                                    </span>
                                    <span class="erp-slide-point">
                                        Driver & vehicle records
                                    </span>
                                    <span class="erp-slide-point">
                                        School-specific permissions
                                    </span>
                                </div>
                            </div>

                            <div class="erp-slide-art">
                                <svg
                                    class="erp-school-art"
                                    viewBox="0 0 560 430"
                                    role="img"
                                    aria-label="School transport illustration"
                                >
                                    <defs>
                                        <linearGradient id="busGrad" x1="0" x2="1">
                                            <stop offset="0" stop-color="#FFD15B"/>
                                            <stop offset="1" stop-color="#F4A836"/>
                                        </linearGradient>
                                        <linearGradient id="roadGrad" x1="0" x2="1">
                                            <stop offset="0" stop-color="#DDE5F2"/>
                                            <stop offset="1" stop-color="#CBD6E7"/>
                                        </linearGradient>
                                    </defs>

                                    <ellipse
                                        cx="282"
                                        cy="371"
                                        rx="216"
                                        ry="27"
                                        fill="url(#roadGrad)"
                                    />

                                    <path
                                        d="M115 369c89-47 226-65 345-16"
                                        fill="none"
                                        stroke="#fff"
                                        stroke-width="5"
                                        stroke-dasharray="25 22"
                                        opacity=".95"
                                    />

                                    <g opacity=".9">
                                        <circle cx="90" cy="174" r="39" fill="#8CD893"/>
                                        <rect x="83" y="182" width="14" height="93" rx="7" fill="#72B76F"/>
                                        <circle cx="475" cy="166" r="44" fill="#84D48C"/>
                                        <rect x="468" y="179" width="14" height="100" rx="7" fill="#6EB06B"/>
                                    </g>

                                    <!-- bus -->
                                    <g transform="translate(105 154)">
                                        <rect x="27" y="51" width="320" height="142" rx="29" fill="url(#busGrad)"/>
                                        <path d="M64 28h206c31 0 52 24 63 52H39c5-30 12-52 25-52z" fill="#F8C04B"/>

                                        <rect x="63" y="49" width="70" height="61" rx="10" fill="#DDF3FF" stroke="#75A8D6" stroke-width="5"/>
                                        <rect x="147" y="49" width="70" height="61" rx="10" fill="#DDF3FF" stroke="#75A8D6" stroke-width="5"/>
                                        <rect x="231" y="49" width="71" height="61" rx="10" fill="#DDF3FF" stroke="#75A8D6" stroke-width="5"/>

                                        <rect x="281" y="120" width="49" height="73" rx="8" fill="#2E67B9"/>
                                        <rect x="43" y="126" width="38" height="25" rx="6" fill="#FFF0A6"/>
                                        <rect x="104" y="132" width="111" height="20" rx="10" fill="#F8E3A0" opacity=".75"/>

                                        <circle cx="100" cy="195" r="30" fill="#2A3858"/>
                                        <circle cx="100" cy="195" r="13" fill="#9CA9BC"/>
                                        <circle cx="277" cy="195" r="30" fill="#2A3858"/>
                                        <circle cx="277" cy="195" r="13" fill="#9CA9BC"/>

                                        <rect x="138" y="159" width="93" height="21" rx="8" fill="#fff" opacity=".86"/>
                                        <text x="185" y="175" text-anchor="middle" font-size="13" font-weight="900" fill="#2C5794" font-family="Arial, sans-serif">SCHOOL BUS</text>
                                    </g>

                                    <!-- shield -->
                                    <g transform="translate(413 75)">
                                        <path
                                            d="M59 10 104 27v39c0 43-27 70-45 80C40 136 14 109 14 66V27z"
                                            fill="#5A46E9"
                                        />
                                        <path
                                            d="m37 71 15 15 31-37"
                                            fill="none"
                                            stroke="#fff"
                                            stroke-width="8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </g>
                                </svg>
                            </div>
                        </article>

                    </div>

                    <div class="erp-slider-foot">
                        <div
                            class="erp-slide-dots"
                            aria-label="School ERP slideshow controls"
                        >
                            <button
                                class="erp-slide-dot is-active"
                                type="button"
                                data-slide-dot="0"
                                aria-label="Student Management"
                            ></button>
                            <button
                                class="erp-slide-dot"
                                type="button"
                                data-slide-dot="1"
                                aria-label="Academics and Attendance"
                            ></button>
                            <button
                                class="erp-slide-dot"
                                type="button"
                                data-slide-dot="2"
                                aria-label="Fees and Accounts"
                            ></button>
                            <button
                                class="erp-slide-dot"
                                type="button"
                                data-slide-dot="3"
                                aria-label="Transport and Security"
                            ></button>
                        </div>

                        <div class="erp-trust-line">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                                <path d="m9 12 2 2 4-4"/>
                            </svg>
                            Secure · Smart · Multi-School Ready
                        </div>
                    </div>

                </div>
            </section>

            <section class="erp-auth-panel">

                <form
                    class="erp-auth-card"
                    method="post"
                    autocomplete="on"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?=e($loginCsrfToken)?>"
                    >

                    <div class="erp-mobile-brand">
                        <img
                            src="assets/images/ecommer-logo.jpg"
                            alt="ECOMMER"
                        >
                    </div>

                    <h1 class="erp-auth-title">
                        Welcome Back!
                    </h1>

                    <p class="erp-auth-subtitle">
                        Sign in to continue to your School ERP account.
                    </p>

                    <?php if ($error): ?>
                        <div
                            class="erp-login-error"
                            role="alert"
                        >
                            <?=e($error)?>
                        </div>
                    <?php endif; ?>

                    <div class="erp-field">
                        <div class="erp-field-label-row">
                            <label for="loginUsername">
                                Username / Email
                            </label>
                        </div>

                        <div class="erp-input-wrap">
                            <span
                                class="erp-input-icon"
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M20 21a8 8 0 0 0-16 0"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </span>

                            <input
                                id="loginUsername"
                                class="form-control"
                                name="username"
                                value="<?=e((string)($_POST['username'] ?? ''))?>"
                                autocomplete="username"
                                placeholder="Enter your username or email"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="erp-field">
                        <div class="erp-field-label-row">
                            <label for="loginPassword">
                                Password
                            </label>
                        </div>

                        <div class="erp-input-wrap">
                            <span
                                class="erp-input-icon"
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect x="4" y="10" width="16" height="11" rx="2"/>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                </svg>
                            </span>

                            <input
                                id="loginPassword"
                                class="form-control"
                                type="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                required
                            >

                            <button
                                id="passwordToggle"
                                class="erp-password-toggle"
                                type="button"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                <svg
                                    id="passwordEyeIcon"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="erp-helper-row">
                        <span>
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                                <path d="m9 12 2 2 4-4"/>
                            </svg>
                            Protected school access
                        </span>

                        <span>
                            Role-based login
                        </span>
                    </div>

                    <button
                        class="erp-login-submit"
                        type="submit"
                    >
                        <span>Login</span>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M5 12h14"/>
                            <path d="m13 6 6 6-6 6"/>
                        </svg>
                    </button>

                    <div class="erp-security-note">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                        Secure login · Your school data is protected
                    </div>

                    <div class="erp-auth-divider">
                        School ERP Modules
                    </div>

                    <div
                        class="erp-module-grid"
                        aria-hidden="true"
                    >
                        <span class="erp-module-chip">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M2 21a7 7 0 0 1 14 0"/>
                            </svg>
                            Students
                        </span>

                        <span class="erp-module-chip">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M2 10 12 5l10 5-10 5z"/>
                                <path d="M6 12v5c3 2 9 2 12 0v-5"/>
                            </svg>
                            Academics
                        </span>

                        <span class="erp-module-chip">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M6 2h9l5 5v15H6z"/>
                                <path d="M14 2v6h6"/>
                            </svg>
                            Fees
                        </span>
                    </div>
                </form>

            </section>

        </section>

        <footer class="erp-login-footer">
            © 2026
            <strong>ECOMMER</strong>
            · Cloud Based Smart Billing Software.
            All rights reserved.
        </footer>

    </div>
</main>

<script>
(() => {
    'use strict';

    const body =
        document.body;

    const intro =
        document.getElementById(
            'erpIntro'
        );

    const hasLoginError =
        body.dataset.loginError === '1';

    const revealLogin = () => {
        body.classList.remove(
            'erp-intro-active'
        );

        body.classList.add(
            'erp-login-ready'
        );

        if (intro) {
            intro.classList.add(
                'is-hidden'
            );

            window.setTimeout(
                () => {
                    intro.remove();
                },
                620
            );
        }

        try {
            sessionStorage.setItem(
                'ecommer_school_erp_login_intro_v4',
                '1'
            );
        } catch (_) {}
    };

    if (
        !hasLoginError
        && intro
    ) {
        let alreadySeen = false;

        try {
            alreadySeen =
                sessionStorage.getItem(
                    'ecommer_school_erp_login_intro_v4'
                ) === '1';
        } catch (_) {}

        if (alreadySeen) {
            revealLogin();
        } else {
            window.setTimeout(
                revealLogin,
                2100
            );
        }
    } else {
        body.classList.remove(
            'erp-intro-active'
        );

        body.classList.add(
            'erp-login-ready'
        );

        if (intro) {
            intro.remove();
        }
    }

    /*
     * Password visibility
     */
    const passwordInput =
        document.getElementById(
            'loginPassword'
        );

    const passwordToggle =
        document.getElementById(
            'passwordToggle'
        );

    if (
        passwordInput
        && passwordToggle
    ) {
        passwordToggle.addEventListener(
            'click',
            () => {
                const showPassword =
                    passwordInput.type
                    === 'password';

                passwordInput.type =
                    showPassword
                        ? 'text'
                        : 'password';

                passwordToggle.setAttribute(
                    'aria-pressed',
                    showPassword
                        ? 'true'
                        : 'false'
                );

                passwordToggle.setAttribute(
                    'aria-label',
                    showPassword
                        ? 'Hide password'
                        : 'Show password'
                );
            }
        );
    }

    /*
     * Automatic left-side School ERP slideshow.
     */
    const slideshow =
        document.getElementById(
            'schoolErpSlideshow'
        );

    const slides =
        Array.from(
            document.querySelectorAll(
                '.erp-slide'
            )
        );

    const dots =
        Array.from(
            document.querySelectorAll(
                '.erp-slide-dot'
            )
        );

    let activeSlide = 0;
    let sliderTimer = null;

    const showSlide = index => {
        if (!slides.length) {
            return;
        }

        activeSlide =
            (
                index
                + slides.length
            )
            % slides.length;

        slides.forEach(
            (slide, slideIndex) => {
                slide.classList.toggle(
                    'is-active',
                    slideIndex
                        === activeSlide
                );
            }
        );

        dots.forEach(
            (dot, dotIndex) => {
                dot.classList.toggle(
                    'is-active',
                    dotIndex
                        === activeSlide
                );

                dot.setAttribute(
                    'aria-current',
                    dotIndex
                        === activeSlide
                        ? 'true'
                        : 'false'
                );
            }
        );
    };

    const stopSlider = () => {
        if (sliderTimer) {
            window.clearInterval(
                sliderTimer
            );

            sliderTimer = null;
        }
    };

    const startSlider = () => {
        stopSlider();

        sliderTimer =
            window.setInterval(
                () => {
                    showSlide(
                        activeSlide + 1
                    );
                },
                4800
            );
    };

    dots.forEach(
        (dot, index) => {
            dot.addEventListener(
                'click',
                () => {
                    showSlide(index);
                    startSlider();
                }
            );
        }
    );

    if (slideshow) {
        slideshow.addEventListener(
            'mouseenter',
            stopSlider
        );

        slideshow.addEventListener(
            'mouseleave',
            startSlider
        );

        slideshow.addEventListener(
            'focusin',
            stopSlider
        );

        slideshow.addEventListener(
            'focusout',
            startSlider
        );
    }

    const reduceMotion =
        window.matchMedia
        && window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

    if (!reduceMotion) {
        startSlider();
    }
})();
</script>

</body>
</html>

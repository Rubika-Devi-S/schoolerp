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
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>School ERP Login | ECOMMER</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>
<link href="assets/css/app.css" rel="stylesheet">

<style>
:root{
    --violet:#5b22e7;
    --blue:#2468df;
    --cyan:#0ecbd4;
    --ink:#102044;
    --text:#283652;
    --muted:#74829a;
    --line:#dbe5f2;
    --card:rgba(255,255,255,.92);
    --shadow:0 28px 90px rgba(28,61,117,.22);
}

*{box-sizing:border-box}

html,body{
    width:100%;
    height:100%;
    margin:0;
}

body.login-page{
    min-height:100vh;
    min-height:100dvh;
    overflow:hidden;
    color:var(--text);
    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
    background:#eef5ff;
}

body.intro-active{
    overflow:hidden;
}

/* =========================================================
   INTRO
   ========================================================= */

.ecommer-intro{
    position:fixed;
    inset:0;
    z-index:20000;
    display:grid;
    place-items:center;
    padding:20px;
    background:#fff;
    transition:
        opacity .65s ease,
        visibility .65s ease;
}

.ecommer-intro.is-hidden{
    opacity:0;
    visibility:hidden;
    pointer-events:none;
}

.ecommer-intro-inner{
    width:min(820px,92vw);
    text-align:center;
}

.ecommer-intro-logo-wrap{
    position:relative;
    width:min(760px,92vw);
    margin:auto;
    animation:
        introLogo .9s cubic-bezier(.16,1,.3,1) both;
}

.ecommer-intro-logo-wrap::after{
    content:"";
    position:absolute;
    inset:34% 8% -14%;
    z-index:-1;
    border-radius:50%;
    opacity:0;
    filter:blur(42px);
    background:linear-gradient(
        90deg,
        rgba(91,34,231,.18),
        rgba(36,104,223,.16),
        rgba(14,203,212,.18)
    );
    animation:introGlow .9s .32s ease forwards;
}

.ecommer-intro-logo{
    display:block;
    width:100%;
    height:auto;
    mix-blend-mode:multiply;
}

.ecommer-intro-caption{
    margin-top:16px;
    color:#738198;
    font-size:12px;
    font-weight:850;
    letter-spacing:.18em;
    text-transform:uppercase;
    opacity:0;
    transform:translateY(10px);
    animation:introCaption .6s .52s ease forwards;
}

.ecommer-intro-bar{
    width:min(290px,60vw);
    height:4px;
    margin:24px auto 0;
    overflow:hidden;
    border-radius:999px;
    background:#edf2f7;
}

.ecommer-intro-bar span{
    display:block;
    width:100%;
    height:100%;
    border-radius:inherit;
    transform:translateX(-100%);
    background:linear-gradient(
        90deg,
        var(--violet),
        var(--blue),
        var(--cyan)
    );
    animation:introBar 1.9s .18s ease forwards;
}

@keyframes introLogo{
    from{
        opacity:0;
        transform:translateY(24px) scale(.95);
    }
    to{
        opacity:1;
        transform:none;
    }
}

@keyframes introGlow{
    to{opacity:1}
}

@keyframes introCaption{
    to{
        opacity:1;
        transform:none;
    }
}

@keyframes introBar{
    to{transform:translateX(0)}
}

/* =========================================================
   FULL-SCREEN COVER BACKGROUND
   ========================================================= */

.login-cover{
    position:fixed;
    inset:0;
    z-index:0;
    overflow:hidden;
    background:
        linear-gradient(
            180deg,
            rgba(248,251,255,.76),
            rgba(238,246,255,.88)
        );
}

.login-cover::before,
.login-cover::after{
    content:"";
    position:absolute;
    top:-4%;
    bottom:-4%;
    width:58%;
    background-repeat:no-repeat;
    background-size:cover;
    opacity:.92;
    filter:
        saturate(.97)
        contrast(.95);
    will-change:transform;
}

.login-cover::before{
    left:-4%;
    background-image:url("assets/images/school-erp-left.jpg");
    background-position:center center;
    animation:leftSceneFloat 13s ease-in-out infinite alternate;
}

.login-cover::after{
    right:-4%;
    background-image:url("assets/images/school-erp-right.jpg");
    background-position:center center;
    animation:rightSceneFloat 14s ease-in-out infinite alternate;
}

@keyframes leftSceneFloat{
    from{
        transform:scale(1.035) translate3d(-5px,0,0);
    }
    to{
        transform:scale(1.085) translate3d(14px,-9px,0);
    }
}

@keyframes rightSceneFloat{
    from{
        transform:scale(1.05) translate3d(5px,0,0);
    }
    to{
        transform:scale(1.095) translate3d(-12px,-8px,0);
    }
}

/* One single continuous overlay makes both images feel like one scene */
.cover-overlay{
    position:absolute;
    inset:0;
    z-index:2;
    pointer-events:none;
    background:
        linear-gradient(
            90deg,
            rgba(241,247,255,.12) 0%,
            rgba(246,250,255,.18) 23%,
            rgba(250,252,255,.96) 43%,
            rgba(250,252,255,.98) 50%,
            rgba(250,252,255,.96) 57%,
            rgba(246,250,255,.18) 77%,
            rgba(241,247,255,.12) 100%
        ),
        linear-gradient(
            180deg,
            rgba(255,255,255,.20),
            rgba(235,245,255,.25)
        );
}

.cover-glow{
    position:absolute;
    z-index:3;
    width:520px;
    height:520px;
    border-radius:50%;
    pointer-events:none;
    filter:blur(4px);
    background:
        radial-gradient(
            circle,
            rgba(46,119,235,.14),
            rgba(14,203,212,.05) 45%,
            transparent 72%
        );
    animation:glowPulse 8s ease-in-out infinite alternate;
}

.cover-glow.left{
    left:12%;
    top:12%;
}

.cover-glow.right{
    right:12%;
    bottom:8%;
    animation-delay:-3s;
}

@keyframes glowPulse{
    from{
        opacity:.55;
        transform:scale(.95);
    }
    to{
        opacity:1;
        transform:scale(1.08);
    }
}

/* =========================================================
   HEADER
   ========================================================= */

.login-header{
    position:fixed;
    z-index:15;
    top:0;
    left:0;
    right:0;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    padding:
        max(18px,env(safe-area-inset-top))
        max(26px,env(safe-area-inset-right))
        10px
        max(26px,env(safe-area-inset-left));
    pointer-events:none;
}

.login-brand{
    display:flex;
    align-items:center;
    gap:15px;
    min-width:0;
    pointer-events:auto;
}

.login-brand img{
    width:180px;
    max-width:40vw;
    height:auto;
    display:block;
    mix-blend-mode:multiply;
}

.login-brand-divider{
    width:1px;
    height:34px;
    background:#d9e3ef;
}

.login-brand-copy{
    min-width:0;
}

.login-brand-copy strong{
    display:block;
    color:#17213f;
    font-size:16px;
    font-weight:900;
}

.login-brand-copy span{
    display:block;
    margin-top:3px;
    color:#738098;
    font-size:9px;
    font-weight:760;
}

.login-security{
    display:flex;
    align-items:center;
    gap:8px;
    padding:9px 12px;
    border:1px solid rgba(199,213,235,.76);
    border-radius:15px;
    background:rgba(255,255,255,.76);
    color:#596881;
    box-shadow:0 10px 28px rgba(37,72,123,.07);
    backdrop-filter:blur(10px);
    font-size:9px;
    font-weight:800;
    pointer-events:auto;
}

.login-security svg{
    width:16px;
    height:16px;
    color:var(--blue);
}

/* =========================================================
   MAIN LOGIN AREA
   ========================================================= */

.login-screen{
    position:relative;
    z-index:10;
    width:100%;
    min-height:100vh;
    min-height:100dvh;
    display:grid;
    grid-template-columns:
        minmax(250px,1fr)
        minmax(390px,460px)
        minmax(250px,1fr);
    align-items:center;
    padding:
        max(92px,calc(env(safe-area-inset-top) + 76px))
        32px
        max(44px,env(safe-area-inset-bottom));
    opacity:0;
    transform:translateY(12px);
    transition:
        opacity .7s ease,
        transform .7s ease;
}

body.login-ready .login-screen{
    opacity:1;
    transform:none;
}

/* side feature labels without panel boxes */
.side-zone{
    position:relative;
    height:100%;
    min-height:620px;
    pointer-events:none;
}

.feature-note{
    position:absolute;
    z-index:6;
    display:flex;
    align-items:center;
    gap:10px;
    max-width:220px;
    padding:9px 11px;
    border-radius:15px;
    background:rgba(255,255,255,.72);
    box-shadow:0 12px 32px rgba(44,79,129,.08);
    backdrop-filter:blur(10px);
    animation:noteFloat 5s ease-in-out infinite;
}

.feature-note strong{
    display:block;
    color:#2052b9;
    font-size:10px;
    font-weight:900;
}

.feature-note small{
    display:block;
    margin-top:2px;
    color:#71809a;
    font-size:8px;
    line-height:1.3;
    font-weight:720;
}

.feature-icon{
    flex:0 0 auto;
    width:31px;
    height:31px;
    display:grid;
    place-items:center;
    border-radius:10px;
    color:#fff;
    background:linear-gradient(
        135deg,
        var(--violet),
        var(--blue)
    );
    box-shadow:0 8px 18px rgba(64,73,200,.16);
}

.feature-icon.cyan{
    background:linear-gradient(
        135deg,
        var(--blue),
        var(--cyan)
    );
}

.feature-icon svg{
    width:15px;
    height:15px;
}

.side-zone.left .feature-note.one{
    left:3%;
    top:19%;
}

.side-zone.left .feature-note.two{
    left:10%;
    bottom:17%;
    animation-delay:-2.2s;
}

.side-zone.right .feature-note.one{
    right:4%;
    top:18%;
    animation-delay:-1.2s;
}

.side-zone.right .feature-note.two{
    right:9%;
    bottom:18%;
    animation-delay:-3.1s;
}

@keyframes noteFloat{
    0%,100%{
        transform:translateY(0);
    }
    50%{
        transform:translateY(-9px);
    }
}

/* =========================================================
   LOGIN CARD
   ========================================================= */

.login-column{
    position:relative;
    z-index:20;
    display:flex;
    align-items:center;
    justify-content:center;
}

.login-column::before{
    content:"";
    position:absolute;
    z-index:-1;
    width:660px;
    height:760px;
    border-radius:50%;
    pointer-events:none;
    background:
        radial-gradient(
            ellipse at center,
            rgba(255,255,255,1) 0%,
            rgba(255,255,255,.98) 54%,
            rgba(255,255,255,.72) 68%,
            rgba(255,255,255,0) 80%
        );
}

.login-card{
    width:min(440px,100%);
    margin:0;
    padding:30px;
    border:1px solid rgba(200,214,235,.86);
    border-radius:28px;
    background:var(--card);
    box-shadow:
        var(--shadow),
        inset 0 1px 0 rgba(255,255,255,.92);
    backdrop-filter:blur(16px);
    opacity:0;
    transform:translateY(16px) scale(.985);
    transition:
        opacity .58s .15s ease,
        transform .58s .15s cubic-bezier(.16,1,.3,1);
}

body.login-ready .login-card{
    opacity:1;
    transform:none;
}

.login-card-logo{
    display:block;
    width:178px;
    max-width:62%;
    height:auto;
    margin:0 auto 13px;
    mix-blend-mode:multiply;
}

.login-card-tag{
    margin:0 0 25px;
    text-align:center;
    color:#687790;
    font-size:9px;
    font-weight:800;
}

.login-title{
    margin:0;
    text-align:center;
    color:#152155;
    font-size:31px;
    line-height:1.08;
    letter-spacing:-.04em;
    font-weight:900;
}

.login-subtitle{
    margin:9px 0 25px;
    text-align:center;
    color:#77859a;
    font-size:11px;
    line-height:1.58;
}

.login-error{
    margin:0 0 17px;
    padding:11px 12px;
    border:1px solid #fecaca;
    border-radius:12px;
    background:#fff7f7;
    color:#b42318;
    font-size:10px;
    font-weight:750;
    line-height:1.45;
}

.login-field{
    margin-bottom:14px;
}

.login-field label{
    display:block;
    margin:0 0 6px;
    color:#3a4962;
    font-size:10px;
    font-weight:850;
}

.login-input-wrap{
    position:relative;
}

.login-input-wrap .form-control{
    min-height:49px;
    padding:12px 45px 12px 14px;
    border:1px solid #dce5f0;
    border-radius:13px;
    background:rgba(251,253,255,.94);
    color:#101828;
    font-size:12px;
    box-shadow:none;
    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}

.login-input-wrap .form-control:focus{
    border-color:#8db4f4;
    background:#fff;
    box-shadow:0 0 0 4px rgba(36,104,223,.08);
}

.input-icon,
.password-toggle{
    position:absolute;
    top:50%;
    right:12px;
    transform:translateY(-50%);
    color:#8d9bae;
}

.input-icon{
    width:17px;
    height:17px;
    pointer-events:none;
}

.input-icon svg,
.password-toggle svg{
    width:100%;
    height:100%;
}

.password-toggle{
    width:33px;
    height:33px;
    display:grid;
    place-items:center;
    padding:0;
    border:0;
    border-radius:10px;
    background:transparent;
}

.password-toggle:hover{
    background:#eff4fa;
    color:#3d4d66;
}

.login-submit{
    width:100%;
    min-height:50px;
    margin-top:4px;
    border:0;
    border-radius:14px;
    color:#fff;
    font-size:11px;
    font-weight:900;
    background:linear-gradient(
        100deg,
        var(--violet),
        var(--blue),
        var(--cyan)
    );
    background-size:180% 100%;
    box-shadow:0 14px 28px rgba(36,104,223,.20);
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        background-position .35s ease;
}

.login-submit:hover{
    transform:translateY(-1px);
    background-position:100% 0;
    box-shadow:0 18px 35px rgba(36,104,223,.25);
}

.login-secure{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    margin-top:17px;
    color:#7e8da2;
    font-size:9px;
    font-weight:760;
}

.login-secure svg{
    width:14px;
    height:14px;
    color:var(--blue);
}

/* =========================================================
   BOTTOM MODULE STRIP
   ========================================================= */

.module-strip{
    position:fixed;
    z-index:15;
    left:50%;
    bottom:max(14px,env(safe-area-inset-bottom));
    transform:translateX(-50%);
    display:flex;
    align-items:center;
    justify-content:center;
    flex-wrap:wrap;
    gap:7px;
    width:min(930px,calc(100vw - 36px));
    pointer-events:none;
}

.module-item{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 9px;
    border-radius:10px;
    background:rgba(255,255,255,.74);
    color:#61708a;
    box-shadow:0 8px 22px rgba(42,76,127,.06);
    backdrop-filter:blur(9px);
    font-size:8px;
    font-weight:830;
}

.module-item svg{
    width:13px;
    height:13px;
    color:var(--blue);
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1200px){
    .login-screen{
        grid-template-columns:
            minmax(210px,.75fr)
            minmax(380px,440px)
            minmax(210px,.75fr);
        padding-left:22px;
        padding-right:22px;
    }

    .feature-note{
        max-width:180px;
    }
}

@media(max-width:980px){
    .login-cover::before{
        width:74%;
        left:-10%;
    }

    .login-cover::after{
        width:74%;
        right:-24%;
        opacity:.55;
    }

    .cover-overlay{
        background:
            linear-gradient(
                90deg,
                rgba(241,247,255,.12),
                rgba(249,252,255,.78) 36%,
                rgba(250,252,255,.98) 58%,
                rgba(248,251,255,.94) 100%
            );
    }

    .login-screen{
        grid-template-columns:
            minmax(180px,.58fr)
            minmax(380px,440px);
        justify-content:center;
    }

    .side-zone.right{
        display:none;
    }

    .module-strip{
        bottom:10px;
    }
}

@media(max-width:760px){
    body.login-page{
        overflow:auto;
    }

    .login-cover::before{
        left:0;
        top:0;
        bottom:0;
        width:100%;
        opacity:.34;
        background-position:42% center;
        transform:none;
        animation:none;
    }

    .login-cover::after{
        display:none;
    }

    .cover-overlay{
        background:
            linear-gradient(
                180deg,
                rgba(248,251,255,.84),
                rgba(246,250,255,.95)
            );
    }

    .cover-glow{
        width:320px;
        height:320px;
    }

    .login-header{
        position:absolute;
        justify-content:center;
        padding:
            max(15px,env(safe-area-inset-top))
            16px
            0;
    }

    .login-brand{
        justify-content:center;
    }

    .login-brand img{
        width:156px;
        max-width:62vw;
    }

    .login-brand-divider,
    .login-brand-copy,
    .login-security{
        display:none;
    }

    .login-screen{
        display:flex;
        align-items:center;
        justify-content:center;
        min-height:100vh;
        min-height:100dvh;
        padding:
            max(92px,calc(env(safe-area-inset-top) + 78px))
            14px
            max(92px,calc(env(safe-area-inset-bottom) + 74px));
    }

    .side-zone{
        display:none;
    }

    .login-column{
        width:100%;
    }

    .login-column::before{
        width:115%;
        height:115%;
    }

    .login-card{
        width:min(460px,100%);
        padding:26px 22px;
        border-radius:24px;
    }

    .module-strip{
        width:calc(100vw - 22px);
        bottom:max(10px,env(safe-area-inset-bottom));
        gap:5px;
    }

    .module-item{
        padding:6px 7px;
        font-size:7px;
    }
}

@media(max-width:480px){
    .ecommer-intro{
        padding:14px;
    }

    .ecommer-intro-logo-wrap{
        width:96vw;
    }

    .ecommer-intro-caption{
        font-size:9px;
    }

    .login-screen{
        align-items:center;
        padding-left:11px;
        padding-right:11px;
    }

    .login-card{
        padding:23px 18px;
        border-radius:22px;
    }

    .login-card-logo{
        width:154px;
    }

    .login-title{
        font-size:27px;
    }

    .login-subtitle{
        font-size:10px;
    }

    .module-item:nth-child(n+5){
        display:none;
    }
}

@media(max-height:720px) and (min-width:761px){
    .login-screen{
        padding-top:76px;
        padding-bottom:60px;
    }

    .login-card{
        padding:22px 26px;
    }

    .login-card-tag{
        margin-bottom:18px;
    }

    .login-title{
        font-size:27px;
    }

    .login-subtitle{
        margin-bottom:18px;
    }

    .login-field{
        margin-bottom:11px;
    }

    .login-input-wrap .form-control{
        min-height:44px;
    }

    .login-submit{
        min-height:45px;
    }
}

@media(prefers-reduced-motion:reduce){
    *,
    *::before,
    *::after{
        animation-duration:.01ms !important;
        animation-iteration-count:1 !important;
        transition-duration:.01ms !important;
        scroll-behavior:auto !important;
    }
}
</style>
</head>

<body
    class="login-page <?= $error !== '' ? 'login-ready has-login-error' : 'intro-active' ?>"
    data-login-error="<?= $error !== '' ? '1' : '0' ?>"
>

<div
    id="ecommerIntro"
    class="ecommer-intro"
    <?= $error !== '' ? 'hidden' : '' ?>
>
    <div class="ecommer-intro-inner">
        <div class="ecommer-intro-logo-wrap">
            <img
                class="ecommer-intro-logo"
                src="assets/images/ecommer-logo.jpg"
                alt="ECOMMER Cloud Based Smart Billing Software"
            >
        </div>

        <div class="ecommer-intro-caption">
            Introducing School ERP
        </div>

        <div class="ecommer-intro-bar">
            <span></span>
        </div>
    </div>
</div>

<div class="login-cover" aria-hidden="true">
    <div class="cover-overlay"></div>
    <div class="cover-glow left"></div>
    <div class="cover-glow right"></div>
</div>

<header class="login-header">
    <div class="login-brand">
        <img
            src="assets/images/ecommer-logo.jpg"
            alt="ECOMMER"
        >

        <span class="login-brand-divider"></span>

        <div class="login-brand-copy">
            <strong>School ERP</strong>
            <span>Cloud Based Smart School Management</span>
        </div>
    </div>

    <div class="login-security">
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
</header>

<main class="login-screen">

    <aside class="side-zone left" aria-hidden="true">
        <div class="feature-note one">
            <span class="feature-icon">
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
            </span>
            <span>
                <strong>Student Management</strong>
                <small>Admissions, profiles, parents and classes</small>
            </span>
        </div>

        <div class="feature-note two">
            <span class="feature-icon cyan">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M16 3v4M8 3v4M3 11h18"/>
                    <path d="m8 15 2 2 4-4"/>
                </svg>
            </span>
            <span>
                <strong>Attendance & Academics</strong>
                <small>Classes, subjects and daily attendance</small>
            </span>
        </div>
    </aside>

    <section class="login-column">

        <form
            class="login-card"
            method="post"
            autocomplete="on"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?=e($loginCsrfToken)?>"
            >

            <img
                class="login-card-logo"
                src="assets/images/ecommer-logo.jpg"
                alt="ECOMMER"
            >

            <p class="login-card-tag">
                Cloud Based Smart School Management
            </p>

            <h1 class="login-title">
                Welcome back
            </h1>

            <p class="login-subtitle">
                Sign in to access your School ERP workspace.
            </p>

            <?php if ($error): ?>
                <div
                    class="login-error"
                    role="alert"
                >
                    <?=e($error)?>
                </div>
            <?php endif; ?>

            <div class="login-field">
                <label for="loginUsername">
                    Username / Email
                </label>

                <div class="login-input-wrap">
                    <input
                        id="loginUsername"
                        class="form-control"
                        name="username"
                        value="<?=e((string)($_POST['username'] ?? ''))?>"
                        autocomplete="username"
                        placeholder="Enter username or email"
                        required
                        autofocus
                    >

                    <span
                        class="input-icon"
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
                </div>
            </div>

            <div class="login-field">
                <label for="loginPassword">
                    Password
                </label>

                <div class="login-input-wrap">
                    <input
                        id="loginPassword"
                        class="form-control"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Enter password"
                        required
                    >

                    <button
                        id="passwordToggle"
                        class="password-toggle"
                        type="button"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <svg
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

            <button
                class="login-submit"
                type="submit"
            >
                Sign In to School ERP
            </button>

            <div class="login-secure">
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
                Secure role-based school access
            </div>
        </form>

    </section>

    <aside class="side-zone right" aria-hidden="true">
        <div class="feature-note one">
            <span class="feature-icon cyan">
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
            </span>
            <span>
                <strong>Role-Based Security</strong>
                <small>School-specific users and permissions</small>
            </span>
        </div>

        <div class="feature-note two">
            <span class="feature-icon">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M3 3v18h18"/>
                    <path d="m7 16 4-5 4 3 5-7"/>
                </svg>
            </span>
            <span>
                <strong>Reports & Multi-School</strong>
                <small>Finance, analytics and isolated schools</small>
            </span>
        </div>
    </aside>

</main>

<footer class="module-strip" aria-hidden="true">
    <span class="module-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
            <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>
        </svg>
        Academics
    </span>

    <span class="module-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="5" width="18" height="16" rx="2"/>
            <path d="M16 3v4M8 3v4M3 11h18"/>
        </svg>
        Attendance
    </span>

    <span class="module-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M5 17H3V6a2 2 0 0 1 2-2h11a3 3 0 0 1 3 3v10"/>
            <circle cx="6.5" cy="17.5" r="2.5"/>
            <circle cx="18.5" cy="17.5" r="2.5"/>
        </svg>
        Transport
    </span>

    <span class="module-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M3 3v18h18"/>
            <path d="m7 16 4-5 4 3 5-7"/>
        </svg>
        Reports
    </span>

    <span class="module-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M20 7h-9"/>
            <path d="M14 17H5"/>
            <circle cx="17" cy="17" r="3"/>
            <circle cx="7" cy="7" r="3"/>
        </svg>
        Permissions
    </span>

    <span class="module-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M3 21h18"/>
            <path d="M6 21V10l6-4 6 4v11"/>
            <path d="M9 21v-6h6v6"/>
        </svg>
        Multi-School
    </span>
</footer>

<script>
(() => {
    'use strict';

    const body = document.body;
    const intro =
        document.getElementById('ecommerIntro');

    const hasLoginError =
        body.dataset.loginError === '1';

    const revealLogin = () => {
        body.classList.remove('intro-active');
        body.classList.add('login-ready');

        if (intro) {
            intro.classList.add('is-hidden');

            window.setTimeout(() => {
                intro.remove();
            }, 700);
        }

        try {
            sessionStorage.setItem(
                'ecommer_school_erp_intro_seen_v3',
                '1'
            );
        } catch (_) {}
    };

    if (!hasLoginError && intro) {
        let alreadySeen = false;

        try {
            alreadySeen =
                sessionStorage.getItem(
                    'ecommer_school_erp_intro_seen_v3'
                ) === '1';
        } catch (_) {}

        if (alreadySeen) {
            revealLogin();
        } else {
            window.setTimeout(
                revealLogin,
                2500
            );
        }
    } else {
        body.classList.remove('intro-active');
        body.classList.add('login-ready');

        if (intro) {
            intro.remove();
        }
    }

    const passwordInput =
        document.getElementById('loginPassword');

    const passwordToggle =
        document.getElementById('passwordToggle');

    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener(
            'click',
            () => {
                const showPassword =
                    passwordInput.type === 'password';

                passwordInput.type =
                    showPassword
                        ? 'text'
                        : 'password';

                passwordToggle.setAttribute(
                    'aria-pressed',
                    showPassword ? 'true' : 'false'
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
})();
</script>

</body>
</html>

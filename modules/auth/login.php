<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) redirect('modules/dashboard/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $db   = Database::getInstance();
            $stmt = $db->select('users', 'username = ? AND status = ?', [$username, 'active']);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                startSession($user);
                redirect('modules/dashboard/index.php');
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600;1,9..144,700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --cream-dk:  #ede9e3;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #2a58b5;
        --accent-bg: #eff6ff;
        --accent-lt: #dbeafe;
        --accent-dk: #1e429f;
    }

    html, body { height: 100%; font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--ink); -webkit-font-smoothing: antialiased; }

    /* ────────────────────────────────────────────
       SHELL — full-viewport split
    ──────────────────────────────────────────── */
    .shell {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 1.1fr 1fr;
    }
    @media (max-width: 880px) {
        .shell { grid-template-columns: 1fr; }
        .left-panel { display: none; }
    }


    /* ────────────────────────────────────────────
       LEFT PANEL
    ──────────────────────────────────────────── */
    .left-panel {
        background: var(--ink);
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 3rem 3.5rem;
        overflow: hidden;
    }

    /* Fine linen-weave texture */
    .left-panel::before {
        content: '';
        position: absolute; inset: 0;
        background-image:
            repeating-linear-gradient(0deg, rgba(255,255,255,.022) 0px, rgba(255,255,255,.022) 1px, transparent 1px, transparent 40px),
            repeating-linear-gradient(90deg, rgba(255,255,255,.022) 0px, rgba(255,255,255,.022) 1px, transparent 1px, transparent 40px);
        pointer-events: none;
    }

    /* Deep accent glow bottom-right */
    .left-panel::after {
        content: '';
        position: absolute; bottom: -200px; right: -200px;
        width: 600px; height: 600px; border-radius: 50%;
        background: radial-gradient(circle at center, rgba(42,88,181,.28) 0%, transparent 65%);
        pointer-events: none;
    }

    /* ── Building illustration (pure CSS) ── */
    .building-wrap {
        position: absolute;
        right: 3rem; bottom: 5rem;
        width: 160px;
        z-index: 1;
        opacity: 0;
        animation: buildUp .8s cubic-bezier(.22,1,.36,1) .6s both;
    }
    @keyframes buildUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }

    .building {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
    }

    .bld-floor {
        display: grid;
        gap: 2px;
        width: 100%;
    }
    .bld-floor.f5 { grid-template-columns: repeat(3,1fr); height: 18px; }
    .bld-floor.f4 { grid-template-columns: repeat(4,1fr); height: 20px; }
    .bld-floor.f3 { grid-template-columns: repeat(4,1fr); height: 22px; }
    .bld-floor.f2 { grid-template-columns: repeat(5,1fr); height: 24px; }
    .bld-floor.f1 { grid-template-columns: repeat(5,1fr); height: 26px; }

    .bunit {
        border-radius: 2px;
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.1);
        transition: background .3s;
    }
    .bunit.lit {
        background: rgba(42,88,181,.45);
        border-color: rgba(42,88,181,.6);
    }
    .bunit.lit2 {
        background: rgba(42,88,181,.2);
        border-color: rgba(42,88,181,.35);
    }

    /* Animated "lights on" pulse */
    .bunit.pulse { animation: unitPulse 3s ease-in-out infinite; }
    .bunit.pulse2 { animation: unitPulse 3s ease-in-out 1.2s infinite; }
    @keyframes unitPulse {
        0%,100% { opacity: .5; }
        50% { opacity: 1; }
    }

    .bld-base {
        width: 120%;
        height: 6px;
        background: rgba(255,255,255,.1);
        border-radius: 2px;
        margin-top: 2px;
    }


    /* ── Panel top area ── */
    .panel-top { position: relative; z-index: 2; }

    .panel-wordmark {
        display: flex; align-items: center; gap: .7rem;
        margin-bottom: 4rem;
    }
    .wm-badge {
        width: 36px; height: 36px; border-radius: 8px;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.14);
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
    }
    .wm-badge img { width: 22px; height: 22px; object-fit: contain; }
    .wm-name {
        font-family: 'Fraunces', serif;
        font-size: 1rem; font-weight: 700;
        color: rgba(255,255,255,.85); letter-spacing: -.01em;
    }

    .panel-tag {
        font-size: .65rem; font-weight: 700; letter-spacing: .18em;
        text-transform: uppercase; color: #7da8f0;
        margin-bottom: .9rem; display: flex; align-items: center; gap: .5rem;
    }
    .panel-tag::before {
        content: ''; display: block;
        width: 20px; height: 1.5px; background: #7da8f0;
    }

    .panel-headline {
        font-family: 'Fraunces', serif;
        font-size: 2.85rem; font-weight: 700; line-height: 1.1;
        color: white; margin-bottom: 1.25rem;
        letter-spacing: -.02em;
    }
    .panel-headline em {
        font-style: italic;
        color: #7da8f0;
    }

    .panel-body {
        font-size: .88rem; color: rgba(255,255,255,.4);
        line-height: 1.7; max-width: 320px;
    }


    /* ── Panel bottom ── */
    .panel-bottom { position: relative; z-index: 2; }

    .feature-list {
        display: flex; flex-direction: column; gap: .6rem;
    }
    .feature-item {
        display: flex; align-items: center; gap: .7rem;
        font-size: .8rem; color: rgba(255,255,255,.45);
        opacity: 0;
        animation: slideIn .5s ease both;
    }
    .feature-item:nth-child(1) { animation-delay: .3s; }
    .feature-item:nth-child(2) { animation-delay: .42s; }
    .feature-item:nth-child(3) { animation-delay: .54s; }
    .feature-item:nth-child(4) { animation-delay: .66s; }
    @keyframes slideIn { from{opacity:0;transform:translateX(-10px)} to{opacity:1;transform:translateX(0)} }

    .fi-ic {
        width: 26px; height: 26px; border-radius: 6px;
        background: rgba(42,88,181,.25);
        border: 1px solid rgba(42,88,181,.4);
        display: flex; align-items: center; justify-content: center;
        font-size: .7rem; color: #7da8f0; flex-shrink: 0;
    }


    /* ────────────────────────────────────────────
       RIGHT PANEL
    ──────────────────────────────────────────── */
    .right-panel {
        display: flex;
        align-items: center; justify-content: center;
        padding: 3rem 2rem;
        background: var(--cream);
        position: relative;
    }

    /* Faint noise overlay */
    .right-panel::before {
        content: '';
        position: absolute; inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)' opacity='.03'/%3E%3C/svg%3E");
        pointer-events: none;
    }

    /* Subtle cream corner accent */
    .right-panel::after {
        content: '';
        position: absolute; top: -80px; right: -80px;
        width: 260px; height: 260px; border-radius: 50%;
        background: radial-gradient(circle, rgba(42,88,181,.07) 0%, transparent 70%);
        pointer-events: none;
    }

    .form-box {
        position: relative; z-index: 1;
        width: 100%; max-width: 390px;
        animation: boxIn .55s cubic-bezier(.22,1,.36,1) .1s both;
    }
    @keyframes boxIn { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }

    /* mobile-only brand */
    .mob-brand { display: none; text-align: center; margin-bottom: 2rem; }
    @media (max-width: 880px) { .mob-brand { display: block; } }
    .mob-brand-name { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); }

    /* ── Box header ── */
    .box-eyebrow {
        font-size: .63rem; font-weight: 700; letter-spacing: .16em;
        text-transform: uppercase; color: var(--accent); margin-bottom: .4rem;
    }
    .box-title {
        font-family: 'Fraunces', serif;
        font-size: 1.8rem; font-weight: 700;
        color: var(--ink); line-height: 1.1;
        margin-bottom: .4rem;
    }
    .box-sub {
        font-size: .82rem; color: var(--ink-mute); margin-bottom: 2rem;
    }

    /* ── Divider ── */
    .hdivide {
        height: 1.5px; background: var(--border-lt); margin: 1.5rem 0;
    }

    /* ── Error ── */
    .err-box {
        display: flex; align-items: center; gap: .6rem;
        padding: .8rem 1rem;
        background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 9px;
        font-size: .82rem; font-weight: 600; color: #b91c1c;
        margin-bottom: 1.25rem;
        animation: shake .35s both;
    }
    @keyframes shake {
        10%,90%{transform:translateX(-2px)}
        20%,80%{transform:translateX(3px)}
        30%,50%,70%{transform:translateX(-3px)}
        40%,60%{transform:translateX(3px)}
    }

    /* ── Fields ── */
    .field {
        margin-bottom: 1.1rem;
    }
    .field label {
        display: block;
        font-size: .68rem; font-weight: 700; letter-spacing: .06em;
        text-transform: uppercase; color: var(--ink-soft);
        margin-bottom: .4rem;
    }
    .f-wrap { position: relative; }

    .f-wrap input {
        width: 100%; height: 46px;
        padding: 0 2.6rem 0 .9rem;
        border: 1.5px solid var(--border); border-radius: 9px;
        font-family: 'DM Sans', sans-serif; font-size: .875rem;
        color: var(--ink); background: white; outline: none;
        transition: border-color .18s, box-shadow .18s;
    }
    .f-wrap input::placeholder { color: var(--ink-mute); }
    .f-wrap input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(42,88,181,.11);
    }

    .f-icon {
        position: absolute; right: .9rem; top: 50%;
        transform: translateY(-50%);
        font-size: .78rem; color: var(--ink-mute);
        pointer-events: none; transition: color .18s;
    }
    .f-wrap:focus-within .f-icon { color: var(--accent); }

    .pw-btn {
        position: absolute; right: .85rem; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer;
        font-size: .78rem; color: var(--ink-mute);
        padding: .25rem; transition: color .15s;
        pointer-events: all;
    }
    .pw-btn:hover { color: var(--accent); }

    /* ── Submit ── */
    .btn-go {
        width: 100%; height: 48px;
        background: var(--ink); color: white; border: none;
        border-radius: 9px;
        font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center; gap: .55rem;
        cursor: pointer; letter-spacing: .01em;
        transition: background .2s, transform .15s, box-shadow .2s;
        margin-top: .25rem;
    }
    .btn-go i { transition: transform .2s; }
    .btn-go:hover {
        background: var(--accent);
        transform: translateY(-1px);
        box-shadow: 0 8px 22px rgba(42,88,181,.32);
    }
    .btn-go:hover i { transform: translateX(4px); }
    .btn-go:active { transform: translateY(0); box-shadow: none; }

    /* ── Footer ── */
    .box-foot {
        margin-top: 2rem; text-align: center;
        font-size: .7rem; color: var(--ink-mute);
        border-top: 1.5px solid var(--border-lt);
        padding-top: 1.5rem;
    }
    </style>
</head>
<body>

<div class="shell">

    <!-- ═══════════════ LEFT PANEL ═══════════════ -->
    <div class="left-panel">

        <!-- CSS building illustration -->
        <div class="building-wrap">
            <div class="building">
                <div class="bld-floor f5">
                    <div class="bunit lit2 pulse"></div>
                    <div class="bunit"></div>
                    <div class="bunit lit pulse2"></div>
                </div>
                <div class="bld-floor f4">
                    <div class="bunit lit pulse"></div>
                    <div class="bunit lit2"></div>
                    <div class="bunit"></div>
                    <div class="bunit lit pulse2"></div>
                </div>
                <div class="bld-floor f3">
                    <div class="bunit"></div>
                    <div class="bunit lit pulse2"></div>
                    <div class="bunit lit"></div>
                    <div class="bunit lit2 pulse"></div>
                </div>
                <div class="bld-floor f2">
                    <div class="bunit lit2"></div>
                    <div class="bunit lit pulse"></div>
                    <div class="bunit"></div>
                    <div class="bunit lit pulse2"></div>
                    <div class="bunit lit2"></div>
                </div>
                <div class="bld-floor f1">
                    <div class="bunit lit pulse"></div>
                    <div class="bunit lit2"></div>
                    <div class="bunit lit pulse2"></div>
                    <div class="bunit"></div>
                    <div class="bunit lit"></div>
                </div>
                <div class="bld-base"></div>
            </div>
        </div>

        <!-- Top: wordmark + headline -->
        <div class="panel-top">
            <div class="panel-wordmark">
                <div class="wm-badge">
                    <img src="<?= BASE_URL ?>assets/images/app_icon.png" alt="<?= APP_NAME ?>">
                </div>
                <span class="wm-name"><?= APP_NAME ?></span>
            </div>

            <div class="panel-tag">Construction ERP</div>

            <h2 class="panel-headline">
                Build with<br><em>clarity.</em><br>Close with<br><em>confidence.</em>
            </h2>

            <p class="panel-body">
                Real-estate management from land acquisition to final handover — all in one place.
            </p>
        </div>

        <!-- Bottom: feature list -->
        <div class="panel-bottom">
            <div class="feature-list">
                <div class="feature-item">
                    <div class="fi-ic"><i class="fas fa-building"></i></div>
                    Flat & unit inventory management
                </div>
                <div class="feature-item">
                    <div class="fi-ic"><i class="fas fa-file-invoice-dollar"></i></div>
                    Bookings, payments & demand letters
                </div>
                <div class="feature-item">
                    <div class="fi-ic"><i class="fas fa-users"></i></div>
                    CRM lead pipeline & follow-ups
                </div>
                <div class="feature-item">
                    <div class="fi-ic"><i class="fas fa-chart-line"></i></div>
                    Project P&amp;L &amp; financial reports
                </div>
            </div>
        </div>

    </div>


    <!-- ═══════════════ RIGHT PANEL ═══════════════ -->
    <div class="right-panel">
        <div class="form-box">

            <!-- Mobile brand -->
            <div class="mob-brand">
                <div class="mob-brand-name"><?= APP_NAME ?></div>
            </div>

            <!-- Heading -->
            <div class="box-eyebrow">Welcome back</div>
            <h1 class="box-title">Sign in to your<br>workspace</h1>
            <p class="box-sub">Enter your credentials to continue.</p>

            <!-- Error -->
            <?php if ($error): ?>
                <div class="err-box">
                    <i class="fas fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" autocomplete="on">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="username">Username</label>
                    <div class="f-wrap">
                        <input type="text" id="username" name="username"
                               placeholder="your.username"
                               required autofocus autocomplete="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <i class="fas fa-at f-icon"></i>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="f-wrap">
                        <input type="password" id="password" name="password"
                               placeholder="••••••••"
                               required autocomplete="current-password">
                        <button type="button" class="pw-btn" onclick="togglePw()" title="Show / hide">
                            <i class="fas fa-eye" id="pwIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="hdivide"></div>

                <button type="submit" class="btn-go">
                    Sign in to Dashboard <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="box-foot">
                &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
            </div>

        </div>
    </div>

</div>

<script>
function togglePw() {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('pwIcon');
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}
</script>

</body>
</html>
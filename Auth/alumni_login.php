<?php
require_once __DIR__ . '/adms_session.php';

/** Safe in-app redirect target after login (basename only). */
function alumni_login_safe_next(?string $next): string
{
    $b = basename((string) $next);
    if ($b !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $b) && !in_array($b, ['index.php', 'admin_login.php', 'logout.php', 'alumni_logout.php'], true)) {
        if ($b === 'community.php' || $b === 'admin_community.php') {
            return 'alumni_community.php';
        }
        if ($b === 'admin_community_post.php') {
            return 'alumni_community_post.php';
        }
        if ($b === 'admin_community_profile.php') {
            return 'alumni_community_profile.php';
        }
        return $b;
    }
    return 'alumni_dashboard.php';
}

if (!empty($_COOKIE[ADMS_SESSION_ALUMNI])) {
    adms_session_start_alumni();
    if (!empty($_SESSION['alumni_id'])) {
        header('Location: ' . alumni_login_safe_next($_GET['next'] ?? ''));
        exit();
    }
    session_write_close();
}

if (adms_admin_session_has_login()) {
    header('Location: dashboard.php');
    exit();
}

adms_session_start_alumni();
include __DIR__ . '/config.php';
require_once __DIR__ . '/community_lib.php';
community_resolve_auth_conflict();

if (empty($_SESSION['alumni_id']) && !empty($_GET['next'])) {
    $_SESSION['alumni_post_login_redirect'] = alumni_login_safe_next($_GET['next']);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id  = trim($_POST['student_id']);
    $national_id = trim($_POST['national_id']);

    if (empty($student_id) || empty($national_id)) {
        $error = "Please enter both your Student ID and National ID.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, student_id FROM alumni WHERE student_id = ? AND national_id = ?");
        $stmt->bind_param("ss", $student_id, $national_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $alumni = $result->fetch_assoc();
            unset($_SESSION['admin_id'], $_SESSION['admin_name']);
            $_SESSION['alumni_id']         = $alumni['id'];
            $_SESSION['alumni_name']       = $alumni['name'];
            $_SESSION['alumni_student_id'] = $alumni['student_id'];
            $_SESSION['user_role']        = 'alumni';
            $dest = $_SESSION['alumni_post_login_redirect'] ?? 'alumni_dashboard.php';
            unset($_SESSION['alumni_post_login_redirect']);
            header('Location: ' . alumni_login_safe_next($dest));
            exit();
        } else {
            $error = "No record found. Please check your Student ID and National ID.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Portal – ADMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --green:       #43a047;
            --green-dark:  #2e7d32;
            --green-light: #e8f5e9;
            --white:       #ffffff;
            --off-white:   #f7faf7;
            --text:        #1b2e1c;
            --muted:       #6b7c6d;
            --border:      #cde8ce;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
            background:
                linear-gradient(rgba(255,255,255,0.82), rgba(255,255,255,0.82)),
                url('COC.jpeg') no-repeat center center fixed;
            background-size: cover;
        }
        body::before {
            content:''; position:fixed; top:-120px; right:-120px;
            width:420px; height:420px;
            background:radial-gradient(circle,rgba(67,160,71,0.13),transparent 70%);
            pointer-events:none;
        }
        body::after {
            content:''; position:fixed; bottom:-100px; left:-100px;
            width:340px; height:340px;
            background:radial-gradient(circle,rgba(46,125,50,0.10),transparent 70%);
            pointer-events:none;
        }
        .login-wrap {
            display:flex; width:100%; max-width:860px;
            border-radius:24px; overflow:hidden;
            box-shadow:0 20px 60px rgba(46,125,50,0.18),0 4px 16px rgba(0,0,0,0.08);
            animation:fadeUp 0.7s cubic-bezier(.22,1,.36,1) both;
        }
        /* Left panel */
        .panel-left {
            flex:1; min-width:280px;
            background:linear-gradient(160deg,var(--green-dark) 0%,#1b5e20 100%);
            padding:52px 40px;
            display:flex; flex-direction:column; justify-content:space-between;
            position:relative; overflow:hidden;
        }
        .panel-left::before {
            content:''; position:absolute; top:-60px; right:-60px;
            width:260px; height:260px; background:rgba(255,255,255,0.05); border-radius:50%;
        }
        .panel-left::after {
            content:''; position:absolute; bottom:-80px; left:-40px;
            width:300px; height:300px; background:rgba(255,255,255,0.04); border-radius:50%;
        }
        .panel-left-logo { display:flex; align-items:center; gap:12px; position:relative; z-index:1; }
        .panel-left-logo img { width:48px; height:48px; border-radius:50%; border:2px solid rgba(255,255,255,0.3); object-fit:cover; }
        .panel-left-logo-text { font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:rgba(255,255,255,0.55); }
        .panel-left-body { position:relative; z-index:1; }
        .panel-left-cap { font-size:10px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,0.45); margin-bottom:14px; }
        .panel-left-title { font-family:'Playfair Display',serif; font-size:clamp(26px,3vw,34px); font-weight:900; color:#fff; line-height:1.2; margin-bottom:18px; }
        .panel-left-title span { color:#a5d6a7; }
        .panel-left-desc { font-size:13px; color:rgba(255,255,255,0.55); line-height:1.75; max-width:260px; }
        .panel-left-footer { font-size:11px; color:rgba(255,255,255,0.28); position:relative; z-index:1; }
        /* Right panel */
        .panel-right {
            flex:1.1; background:var(--white); padding:52px 44px;
            display:flex; flex-direction:column; justify-content:center;
        }
        .form-eyebrow { display:inline-flex; align-items:center; gap:7px; font-size:10.5px; font-weight:600; letter-spacing:2.5px; text-transform:uppercase; color:var(--green); margin-bottom:10px; }
        .form-eyebrow::before { content:''; width:18px; height:2px; background:var(--green); border-radius:2px; }
        .form-title { font-family:'Playfair Display',serif; font-size:28px; font-weight:700; color:var(--text); margin-bottom:6px; }
        .form-sub { font-size:13px; color:var(--muted); margin-bottom:32px; }
        .alert { padding:12px 15px; border-radius:10px; font-size:13px; margin-bottom:22px; background:#ffebee; border:1px solid #ef9a9a; color:#c62828; display:flex; align-items:flex-start; gap:8px; }
        .field { display:flex; flex-direction:column; gap:7px; margin-bottom:18px; }
        .field label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1.2px; color:var(--muted); }
        .field-wrap { position:relative; }
        .field-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:16px; pointer-events:none; opacity:0.5; }
        .field input {
            width:100%; background:var(--off-white); border:1.5px solid var(--border);
            border-radius:10px; padding:12px 14px 12px 40px;
            color:var(--text); font-family:'DM Sans',sans-serif; font-size:14px;
            outline:none; transition:border-color .2s,box-shadow .2s,background .2s;
        }
        .field input:focus { border-color:var(--green); background:var(--white); box-shadow:0 0 0 3px rgba(67,160,71,0.12); }
        .field input::placeholder { color:#b0bfb1; }
        .btn-submit {
            width:100%;
            background:linear-gradient(135deg,var(--green),var(--green-dark));
            color:#fff; border:none; border-radius:10px; padding:14px;
            font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600;
            cursor:pointer; transition:all .25s;
            display:flex; align-items:center; justify-content:center; gap:8px;
            margin-top:8px; box-shadow:0 4px 14px rgba(46,125,50,0.3);
        }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(46,125,50,0.36); }
        .btn-submit:active { transform:scale(0.98); }
        .form-hint { text-align:center; margin-top:22px; font-size:12px; color:var(--muted); line-height:1.6; }
        .back-link { text-align:center; margin-top:16px; }
        .back-link a { font-size:12.5px; color:var(--green); text-decoration:none; font-weight:500; }
        .back-link a:hover { opacity:0.7; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
        @media(max-width:640px) {
            .panel-left { display:none; }
            .panel-right { padding:40px 28px; }
        }
    </style>
</head>
<body>
<div class="login-wrap">

    <div class="panel-left">
        <div class="panel-left-logo">
            <img src="logo.png" alt="COC Logo">
            <span class="panel-left-logo-text">COC — QU</span>
        </div>
        <div class="panel-left-body">
            <div class="panel-left-cap">Alumni Portal</div>
            <div class="panel-left-title">Welcome<br>back,<br><span>Graduate.</span></div>
            <div class="panel-left-desc">
                Access your academic record, graduation details, and personal data from the College of Computer.
            </div>
        </div>
        <div class="panel-left-footer">© 2026 Qassim University — College of Computer</div>
    </div>

    <div class="panel-right">
        <div class="form-eyebrow">Secure Access</div>
        <div class="form-title">Sign in to your profile</div>
        <div class="form-sub">Use your university credentials to continue.</div>

        <?php if ($error): ?>
            <div class="alert">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="field">
                <label>Student ID — رقم الطالب</label>
                <div class="field-wrap">
                    <span class="field-icon">🎓</span>
                    <input type="text" name="student_id"
                           placeholder="e.g. 441100123"
                           value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>"
                           required autofocus>
                </div>
            </div>
            <div class="field">
                <label>National ID — السجل المدني</label>
                <div class="field-wrap">
                    <span class="field-icon">🪪</span>
                    <input type="password" name="national_id"
                           placeholder="Your 10-digit national ID"
                           required>
                </div>
            </div>
            <button type="submit" class="btn-submit">🔐 Sign In to Portal</button>
        </form>

        <p class="form-hint">
            Your login credentials are your <strong>Student ID</strong><br>
            and <strong>National ID</strong> as registered in the university system.
        </p>
        <div class="back-link">
            <a href="index.php">← Back to main portal</a>
        </div>
    </div>

</div>
</body>
</html>

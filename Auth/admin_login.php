<?php
session_start();
include "config.php";

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = md5(trim($_POST['password']));

    $stmt = $conn->prepare("SELECT id, name FROM admins WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – ADMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">

        <img src="logo.png" alt="COC Logo" class="login-logo">

        <h2>Admin Portal</h2>
        <p class="login-sub">College of Computer — Qassim University</p>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div class="field" style="margin-top:14px;">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary"
                    style="width:100%;justify-content:center;padding:11px;margin-top:20px;font-size:14px;">
                🔐 Sign In
            </button>
        </form>

        <p style="margin-top:22px;font-size:12px;color:var(--muted);">
            © 2026 Qassim University — College of Computer
        </p>

    </div>
</div>
</body>
</html>

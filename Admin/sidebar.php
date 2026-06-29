<?php
$current = basename($_SERVER['PHP_SELF']);

/* ----------------------------------------------------------------
 * Unread Support count (best-effort).
 * ---------------------------------------------------------------- */
$admin_support_unread = 0;
if (!empty($_SESSION['admin_id'])) {
    try {
        if (!isset($pdo) || !$pdo) {
            @include_once __DIR__ . '/config.php';
        }
        if (isset($pdo) && $pdo) {
            @require_once __DIR__ . '/support_lib.php';
            if (function_exists('support_table_ready') && support_table_ready($pdo)) {
                $admin_support_unread = support_count_unread_for_admin($pdo);
            }
        }
    } catch (Throwable $e) {
        $admin_support_unread = 0;
    }
}
?>
<aside class="sidebar">

    <div class="sidebar-logo">
        <img src="logo.png" alt="COC">
        <div class="sidebar-logo-text">
            <span class="badge-label">COC — QU</span>
            <h2>ADMS</h2>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section-label">Main Menu</span>

        <a href="dashboard.php" class="nav-link <?php echo $current==='dashboard.php' ? 'active':''; ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="alumni.php" class="nav-link <?php echo $current==='alumni.php' ? 'active':''; ?>">
            <span class="nav-icon">🎓</span> Alumni Records
        </a>
        <a href="admin_support.php" class="nav-link <?php echo $current==='admin_support.php' ? 'active':''; ?>">
            <span class="nav-icon">🛟</span> Support Inbox
            <?php if ($admin_support_unread > 0): ?>
                <span class="nav-badge"><?php echo (int) $admin_support_unread; ?></span>
            <?php endif; ?>
        </a>

        <span class="nav-section-label" style="margin-top:18px;">System</span>

        <a href="add_alumni.php" class="nav-link <?php echo $current==='add_alumni.php' ? 'active':''; ?>">
            <span class="nav-icon">➕</span> Add Alumni
        </a>
        <a href="import_alumni.php" class="nav-link <?php echo $current==='import_alumni.php' ? 'active':''; ?>">
            <span class="nav-icon">📥</span> Import Excel
        </a>
        <a href="export_alumni.php" class="nav-link <?php echo $current==='export_alumni.php' ? 'active':''; ?>">
            <span class="nav-icon">📤</span> Generate Report
        </a>
        <a href="announcements.php" class="nav-link <?php echo in_array($current,['announcements.php','add_announcement.php','edit_announcement.php']) ? 'active':''; ?>">
            <span class="nav-icon">📣</span> Announcements
        </a>
        <a href="admin_community.php" class="nav-link <?php echo in_array($current,['admin_community.php','admin_community_post.php','admin_community_profile.php']) ? 'active':''; ?>">
            <span class="nav-icon">💬</span> Community
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-pill">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                <div class="admin-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <span>🚪</span> Logout
        </a>
    </div>

</aside>

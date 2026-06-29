<?php
$current = basename($_SERVER['PHP_SELF']);

/* ----------------------------------------------------------------
 * Unread Support count (best-effort — never blocks page render).
 * ---------------------------------------------------------------- */
$alumni_support_unread = 0;
if (!empty($_SESSION['alumni_id'])) {
    try {
        if (!isset($pdo) || !$pdo) {
            // config.php may not have been included by every page that uses the sidebar
            @include_once __DIR__ . '/config.php';
        }
        if (isset($pdo) && $pdo) {
            @require_once __DIR__ . '/support_lib.php';
            if (function_exists('support_table_ready') && support_table_ready($pdo)) {
                $alumni_support_unread = support_count_unread_for_alumni($pdo, (int) $_SESSION['alumni_id']);
            }
        }
    } catch (Throwable $e) {
        $alumni_support_unread = 0;
    }
}
?>
<aside class="sidebar">

    <div class="sidebar-logo">
        <img src="logo.png" alt="COC">
        <div class="sidebar-logo-text">
            <span class="badge-label">COC — QU</span>
            <h2>Alumni Portal</h2>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section-label">Menu</span>

        <a href="alumni_dashboard.php" class="nav-link <?php echo $current==='alumni_dashboard.php' ? 'active':''; ?>">
            <span class="nav-icon">🏠</span> Home
        </a>
        <a href="alumni_profile.php" class="nav-link <?php echo $current==='alumni_profile.php' ? 'active':''; ?>">
            <span class="nav-icon">👤</span> My Profile
        </a>
        <a href="alumni_announcements.php" class="nav-link <?php echo $current==='alumni_announcements.php' ? 'active':''; ?>">
            <span class="nav-icon">📢</span> Announcements
        </a>
        <a href="alumni_community.php" class="nav-link <?php echo in_array($current,['alumni_community.php','alumni_community_post.php','alumni_community_profile.php']) ? 'active':''; ?>">
            <span class="nav-icon">💬</span> Community
        </a>
        <a href="alumni_search.php" class="nav-link <?php echo $current==='alumni_search.php' ? 'active':''; ?>">
            <span class="nav-icon">🔍</span> Alumni Search
        </a>

        <span class="nav-section-label" style="margin-top:18px;">Help</span>

        <a href="alumni_support.php" class="nav-link <?php echo $current==='alumni_support.php' ? 'active':''; ?>">
            <span class="nav-icon">🛟</span> Support
            <?php if ($alumni_support_unread > 0): ?>
                <span class="nav-badge"><?php echo (int) $alumni_support_unread; ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-pill">
            <div class="admin-avatar">
                <?php echo mb_strtoupper(mb_substr($_SESSION['alumni_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($_SESSION['alumni_name'] ?? 'Alumni'); ?></div>
                <div class="admin-role">Graduate</div>
            </div>
        </div>
        <a href="alumni_logout.php" class="logout-link">
            <span>🚪</span> Logout
        </a>
    </div>

</aside>

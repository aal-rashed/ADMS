<?php
require_once __DIR__ . '/adms_session.php';
adms_session_start_alumni();
include "config.php";

if (!isset($_SESSION['alumni_id'])) {
    header("Location: alumni_login.php");
    exit();
}

$id   = (int) $_SESSION['alumni_id'];
$stmt = $conn->prepare("SELECT * FROM alumni WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { session_destroy(); header("Location: alumni_login.php"); exit(); }
$a = $result->fetch_assoc();
$stmt->close();

function e($v) { return htmlspecialchars($v ?? ''); }

// ── Directory stats ──
$total_alumni = $conn->query("SELECT COUNT(*) as c FROM alumni")->fetch_assoc()['c'];
$total_honor  = $conn->query("SELECT COUNT(*) as c FROM alumni WHERE honor_rank IS NOT NULL AND TRIM(honor_rank) != '' AND honor_rank != 'null'")->fetch_assoc()['c'];
$total_terms  = $conn->query("SELECT COUNT(DISTINCT graduation_term) as c FROM alumni WHERE graduation_term IS NOT NULL")->fetch_assoc()['c'];
$total_majors = $conn->query("SELECT COUNT(DISTINCT major) as c FROM alumni WHERE major IS NOT NULL AND TRIM(major) != ''")->fetch_assoc()['c'];

// ── Classmates in same term ──
$classmates = 0;
if (!empty($a['graduation_term'])) {
    $cs = $conn->prepare("SELECT COUNT(*) as c FROM alumni WHERE graduation_term = ? AND id != ?");
    $cs->bind_param("si", $a['graduation_term'], $id);
    $cs->execute();
    $classmates = $cs->get_result()->fetch_assoc()['c'];
    $cs->close();
}

// ── Announcements (from DB) ──
$announcements = [];
$ann_icons = [
    'job'          => '💼',
    'event'        => '🎓',
    'announcement' => '📢',
    'alert'        => '⚠️',
    'link'         => '🔗',
    'statement'    => '📋',
    'general'      => '📣',
];
$check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($check_table && $check_table->num_rows > 0) {
    $has_pinned = false;
    $col_check  = $conn->query("SHOW COLUMNS FROM announcements LIKE 'is_pinned'");
    if ($col_check && $col_check->num_rows > 0) $has_pinned = true;

    $order = $has_pinned ? "ORDER BY is_pinned DESC, created_at DESC" : "ORDER BY created_at DESC";
    $res   = $conn->query("SELECT id, type, title, content, created_at FROM announcements $order LIMIT 2");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $key = strtolower(trim($r['type'] ?? 'general'));
            $announcements[] = [
                'icon'  => $ann_icons[$key] ?? '📌',
                'title' => $r['title'],
                'date'  => !empty($r['created_at']) ? date('Y-m-d', strtotime($r['created_at'])) : '',
                'body'  => $r['content'] ?? '',
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home – Alumni Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:28px; }
        .stat-card  { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; display:flex; align-items:center; gap:14px; box-shadow:0 2px 8px var(--shadow); transition:transform .2s; }
        .stat-card:hover { transform:translateY(-2px); }
        .stat-icon  { font-size:24px; width:48px; height:48px; background:var(--green-light); border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .stat-info  { display:flex; flex-direction:column; }
        .stat-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.9px; color:var(--muted); }
        .stat-value { font-size:26px; font-weight:700; color:var(--green-dark); line-height:1.1; }

        .welcome-card {
            background:linear-gradient(135deg,var(--green-dark) 0%,#1b5e20 100%);
            border-radius:var(--radius-lg); padding:26px 32px;
            display:flex; align-items:center; gap:22px;
            margin-bottom:28px; position:relative; overflow:hidden;
            box-shadow:0 6px 24px rgba(46,125,50,0.25);
        }
        .welcome-card::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(255,255,255,0.05); border-radius:50%; }
        .welcome-card::after  { content:''; position:absolute; bottom:-50px; left:220px; width:180px; height:180px; background:rgba(255,255,255,0.04); border-radius:50%; }
        .wc-avatar { width:68px; height:68px; border-radius:50%; background:rgba(255,255,255,0.15); border:3px solid rgba(255,255,255,0.28); display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:700; color:#fff; font-family:var(--font-display); flex-shrink:0; position:relative; z-index:1; }
        .wc-body { position:relative; z-index:1; flex:1; }
        .wc-eyebrow { font-size:10px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,0.45); margin-bottom:4px; }
        .wc-name  { font-family:var(--font-display); font-size:clamp(17px,2.5vw,22px); font-weight:700; color:#fff; line-height:1.2; margin-bottom:8px; }
        .wc-pills { display:flex; flex-wrap:wrap; gap:7px; }
        .wc-pill  { display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.18); border-radius:100px; padding:4px 12px; font-size:12px; color:rgba(255,255,255,0.85); }
        .wc-pill-honor { background:#f9a825; color:#5d4037; border:none; font-weight:700; }
        .wc-date { position:relative; z-index:1; text-align:right; color:rgba(255,255,255,0.5); font-size:12px; white-space:nowrap; }

        .dash-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        @media(max-width:860px) { .dash-row { grid-template-columns:1fr; } }

        .widget-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; box-shadow:0 2px 10px var(--shadow); }
        .widget-head { display:flex; align-items:center; justify-content:space-between; padding:13px 18px; background:var(--off-white); border-bottom:1px solid var(--border); }
        .wh-left { display:flex; align-items:center; gap:10px; }
        .wh-icon  { width:32px; height:32px; border-radius:8px; background:var(--green-light); display:flex; align-items:center; justify-content:center; font-size:16px; }
        .wh-title { font-size:13px; font-weight:600; color:var(--text); }
        .wh-sub   { font-size:11px; color:var(--muted); }
        .widget-link { font-size:12px; color:var(--green); font-weight:500; text-decoration:none; transition:color .2s; }
        .widget-link:hover { color:var(--green-dark); text-decoration:underline; }
        .widget-body { padding:18px; }

        .pm-rows { display:flex; flex-direction:column; }
        .pm-row  { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border); gap:12px; }
        .pm-row:last-child { border-bottom:none; }
        .pm-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); }
        .pm-value { font-size:13px; color:var(--text); font-weight:500; text-align:right; }
        .pm-honor { display:inline-block; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:700; background:#fff8e1; color:#e65100; border:1px solid #ffe082; }
        .pm-gpa   { font-weight:700; color:var(--green-dark); font-size:15px; }

        .ann-list { display:flex; flex-direction:column; }
        .ann-item { display:flex; gap:13px; padding:13px 0; border-bottom:1px solid var(--border); }
        .ann-item:last-child { border-bottom:none; }
        .ann-dot  { width:36px; height:36px; border-radius:10px; background:var(--green-light); display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; margin-top:2px; }
        .ann-content { flex:1; }
        .ann-title { font-size:13px; font-weight:600; color:var(--text); margin-bottom:2px; line-height:1.3; }
        .ann-date  { font-size:11px; color:var(--muted); margin-bottom:4px; }
        .ann-body  { font-size:12px; color:var(--muted); line-height:1.6; }

        .section-label { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:14px; }
        .actions-grid  { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; margin-bottom:28px; }
        .action-card   { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px; display:flex; align-items:center; gap:14px; text-decoration:none; color:var(--text); box-shadow:0 2px 8px var(--shadow); transition:all .2s; }
        .action-card:hover { border-color:var(--green); transform:translateY(-2px); }
        .action-icon   { font-size:22px; width:44px; height:44px; background:var(--green-light); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .action-title  { font-size:13px; font-weight:600; }
        .action-desc   { font-size:11px; color:var(--muted); margin-top:2px; }

        @media(max-width:600px) { .welcome-card{flex-direction:column;} .wc-date{text-align:left;} }
    </style>
</head>
<body>
<div class="layout">

    <?php include "alumni_sidebar.php"; ?>

    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Home</h1>
                <p class="page-sub">Welcome back, <?php echo e($a['name']); ?> 👋 — <?php echo date('l, F j, Y'); ?></p>
            </div>
        </div>

        <!-- Welcome hero -->
        <div class="welcome-card">
            <div class="wc-avatar"><?php echo mb_substr($a['name'] ?? 'A', 0, 1); ?></div>
            <div class="wc-body">
                <div class="wc-eyebrow">Alumni Profile</div>
                <div class="wc-name"><?php echo e($a['name']); ?></div>
                <div class="wc-pills">
                    <?php if ($a['student_id']): ?><span class="wc-pill">🎓 <?php echo e($a['student_id']); ?></span><?php endif; ?>
                    <?php if ($a['major']): ?><span class="wc-pill">📚 <?php echo e($a['major']); ?></span><?php endif; ?>
                    <?php if ($a['graduation_term']): ?><span class="wc-pill">📅 Term <?php echo e($a['graduation_term']); ?></span><?php endif; ?>
                    <?php if ($a['academic_degree']): ?><span class="wc-pill">🏛️ <?php echo e($a['academic_degree']); ?></span><?php endif; ?>
                    <?php if (!empty($a['honor_rank'])): ?><span class="wc-pill wc-pill-honor">🏅 <?php echo e($a['honor_rank']); ?></span><?php endif; ?>
                </div>
            </div>
            <div class="wc-date"><?php echo date('d M Y'); ?></div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-info">
                    <div class="stat-label">Total Alumni</div>
                    <div class="stat-value"><?php echo number_format($total_alumni); ?></div>
                </div>
            </div>
            
            
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <div class="stat-label">Majors</div>
                    <div class="stat-value"><?php echo number_format($total_majors); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏅</div>
                <div class="stat-info">
                    <div class="stat-label">Honor Students</div>
                    <div class="stat-value"><?php echo number_format($total_honor); ?></div>
                </div>
            </div>
        </div>

        <!-- Widgets row -->
        <div class="dash-row">

            <!-- Profile snapshot -->
            <div class="widget-card">
                <div class="widget-head">
                    <div class="wh-left">
                        <div class="wh-icon">👤</div>
                        <div>
                            <div class="wh-title">My Profile</div>
                            <div class="wh-sub">Academic snapshot</div>
                        </div>
                    </div>
                    <a href="alumni_profile.php" class="widget-link">View full →</a>
                </div>
                <div class="widget-body">
                    <div class="pm-rows">
                        <div class="pm-row">
                            <span class="pm-label">GPA</span>
                            <span class="pm-value pm-gpa"><?php echo ($a['gpa'] !== null) ? number_format($a['gpa'],2) : '—'; ?></span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">Grade — التقدير</span>
                            <span class="pm-value"><?php echo e($a['academic_grade']) ?: '—'; ?></span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">College</span>
                            <span class="pm-value"><?php echo e($a['college']) ?: '—'; ?></span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">Major</span>
                            <span class="pm-value"><?php echo e($a['major']) ?: '—'; ?></span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">Campus</span>
                            <span class="pm-value"><?php echo e($a['campus']) ?: '—'; ?></span>
                        </div>
                        <?php if (!empty($a['honor_rank'])): ?>
                        <div class="pm-row">
                            <span class="pm-label">Honor Rank</span>
                            <span class="pm-honor">🏅 <?php echo e($a['honor_rank']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Announcements snapshot -->
            <div class="widget-card">
                <div class="widget-head">
                    <div class="wh-left">
                        <div class="wh-icon">📢</div>
                        <div>
                            <div class="wh-title">Announcements</div>
                            <div class="wh-sub">Latest updates</div>
                        </div>
                    </div>
                    <a href="alumni_announcements.php" class="widget-link">View all →</a>
                </div>
                <div class="widget-body">
                    <div class="ann-list">
                        <?php if (empty($announcements)): ?>
                        <div class="ann-item" style="border-bottom:none;">
                            <div class="ann-dot">📭</div>
                            <div class="ann-content">
                                <div class="ann-title">No announcements yet</div>
                                <div class="ann-body">Check back soon for updates from the college.</div>
                            </div>
                        </div>
                        <?php else: ?>
                        <?php foreach (array_slice($announcements, 0, 2) as $ann): ?>
                        <div class="ann-item">
                            <div class="ann-dot"><?php echo $ann['icon']; ?></div>
                            <div class="ann-content">
                                <div class="ann-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                <div class="ann-date"><?php echo htmlspecialchars($ann['date']); ?></div>
                                <div class="ann-body"><?php echo htmlspecialchars(mb_strimwidth(strip_tags($ann['body']), 0, 140, '…')); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Quick Actions -->
        <div class="section-label">Quick Actions</div>
        <div class="actions-grid">
            <a class="action-card" href="alumni_profile.php">
                <div class="action-icon">👤</div>
                <div><div class="action-title">My Profile</div><div class="action-desc">View your full academic record</div></div>
            </a>
            <a class="action-card" href="alumni_announcements.php">
                <div class="action-icon">📢</div>
                <div><div class="action-title">Announcements</div><div class="action-desc">News and college updates</div></div>
            </a>
            <a class="action-card" href="alumni_search.php">
                <div class="action-icon">🔍</div>
                <div><div class="action-title">Alumni Search</div><div class="action-desc">Browse the graduate directory</div></div>
            </a>
        </div>

    </main>
</div>
</body>
</html>

<?php
require_once __DIR__ . '/adms_session.php';
adms_session_start_alumni();
include "config.php";

if (!isset($_SESSION['alumni_id'])) {
    header("Location: alumni_login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: alumni_announcements.php");
    exit();
}

$id   = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: alumni_announcements.php");
    exit();
}
$ann = $result->fetch_assoc();
$stmt->close();

$type   = strtolower(trim($ann['type'] ?? 'general'));
$pinned = !empty($ann['is_pinned']);

$type_map = [
    'general'      => ['bg'=>'#e8f5e9','color'=>'#2e7d32','label'=>'General',      'icon'=>'📣'],
    'announcement' => ['bg'=>'#e3f2fd','color'=>'#1565c0','label'=>'Announcement',  'icon'=>'📢'],
    'alert'        => ['bg'=>'#ffebee','color'=>'#b71c1c','label'=>'Alert',         'icon'=>'⚠️'],
    'job'          => ['bg'=>'#fff3e0','color'=>'#e65100','label'=>'Job',           'icon'=>'💼'],
    'jobs'         => ['bg'=>'#fff3e0','color'=>'#e65100','label'=>'Jobs',          'icon'=>'💼'],
    'event'        => ['bg'=>'#f3e5f5','color'=>'#6a1b9a','label'=>'Event',         'icon'=>'🗓'],
    'events'       => ['bg'=>'#f3e5f5','color'=>'#6a1b9a','label'=>'Events',        'icon'=>'🗓'],
    'academic'     => ['bg'=>'#e8f5e9','color'=>'#2e7d32','label'=>'Academic',      'icon'=>'🎓'],
    'important'    => ['bg'=>'#ffebee','color'=>'#b71c1c','label'=>'Important',     'icon'=>'🚨'],
];
$c = $type_map[$type] ?? ['bg'=>'#f5f5f5','color'=>'#555','label'=>ucfirst($type),'icon'=>'📌'];

$content    = trim($ann['content']    ?? '');
$image_path = trim($ann['image_path'] ?? '');
$url        = trim($ann['url']        ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ann['title']); ?> – Announcements</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .post-wrap { max-width:780px; }

        .post-card {
            background:var(--white); border:1px solid var(--border);
            border-radius:var(--radius-lg); overflow:hidden;
            box-shadow:0 4px 18px var(--shadow);
        }
        .post-top-bar { height:5px; background:<?php echo $c['color']; ?>; }

        .post-header { padding:28px 32px 22px; border-bottom:1px solid var(--border); }
        .post-meta { display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
        .cat-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 13px; border-radius:20px; font-size:12px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; background:<?php echo $c['bg']; ?>; color:<?php echo $c['color']; ?>; }
        .pinned-label { font-size:12px; font-weight:600; color:var(--green-dark); background:var(--green-light); border:1px solid #a5d6a7; border-radius:20px; padding:3px 11px; }
        .post-date { font-size:13px; color:var(--muted); margin-left:auto; }
        .post-title { font-family:var(--font-display); font-size:clamp(20px,3vw,26px); color:var(--text); line-height:1.3; }

        /* Image */
        .post-image-wrap { padding:24px 32px 0; }
        .post-image-wrap img { width:100%; max-height:420px; object-fit:cover; border-radius:var(--radius-md); border:1px solid var(--border); display:block; box-shadow:0 4px 16px rgba(0,0,0,0.10); }

        /* Content */
        .post-body { padding:24px 32px; font-size:15px; color:#2e3d2f; line-height:1.9; }
        .post-body p  { margin-bottom:12px; }
        .post-body p:last-child { margin-bottom:0; }
        .post-body a  { color:var(--green-dark); font-weight:600; text-decoration:underline; text-underline-offset:3px; }
        .post-body a:hover { color:var(--green); }
        .post-body img { max-width:100%; border-radius:var(--radius-sm); margin:12px 0; display:block; }
        .post-body ul, .post-body ol { padding-left:22px; margin-bottom:12px; }
        .post-body li { margin-bottom:5px; }
        .post-empty { padding:32px; color:var(--muted); font-style:italic; font-size:14px; }

        /* Link box */
        .post-link-box {
            margin:0 32px 24px;
            background:var(--green-light); border:1px solid #a5d6a7;
            border-radius:var(--radius-md); padding:16px 20px;
            display:flex; align-items:center; gap:14px;
        }
        .post-link-icon { font-size:22px; flex-shrink:0; }
        .post-link-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:3px; }
        .post-link-url { font-size:13px; font-weight:600; color:var(--green-dark); word-break:break-all; }
        .post-link-url:hover { text-decoration:underline; }
        .post-link-btn { margin-left:auto; flex-shrink:0; }

        /* Footer */
        .post-footer { padding:16px 32px; border-top:1px solid var(--border); background:var(--off-white); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
        .post-footer-date { font-size:12px; color:var(--muted); }

        @media(max-width:600px) {
            .post-header,.post-body,.post-footer,.post-image-wrap,.post-link-box { padding-left:18px; padding-right:18px; }
            .post-link-box { margin-left:18px; margin-right:18px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <?php include "alumni_sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <a href="alumni_announcements.php" class="back-link">← Back to Announcements</a>
        </div>

        <div class="post-wrap">
            <div class="post-card">

                <div class="post-top-bar"></div>

                <!-- Header -->
                <div class="post-header">
                    <div class="post-meta">
                        <span class="cat-badge"><?php echo $c['icon']; ?> <?php echo $c['label']; ?></span>
                        <?php if ($pinned): ?><span class="pinned-label">📌 Pinned</span><?php endif; ?>
                        <span class="post-date">
                            <?php echo date('l, F j, Y', strtotime($ann['created_at'])); ?>
                            &nbsp;·&nbsp;
                            <?php echo date('g:i A', strtotime($ann['created_at'])); ?>
                        </span>
                    </div>
                    <div class="post-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                </div>

                <!-- Image -->
                <?php if ($image_path !== ''): ?>
                    <div class="post-image-wrap">
                        <a href="<?php echo htmlspecialchars($image_path); ?>" target="_blank" rel="noopener noreferrer" title="Click to open full image">
                            <img src="<?php echo htmlspecialchars($image_path); ?>"
                                 alt="<?php echo htmlspecialchars($ann['title']); ?>"
                                 style="cursor:zoom-in;">
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Content -->
                <?php if ($content !== ''): ?>
                    <div class="post-body">
                        <?php
                        // If content looks like plain text (no HTML tags), wrap lines in <p>
                        if (strip_tags($content) === $content) {
                            foreach (array_filter(array_map('trim', explode("\n", $content))) as $line) {
                                echo '<p>' . htmlspecialchars($line) . '</p>';
                            }
                        } else {
                            // Render as HTML, open links in new tab
                            $rendered = preg_replace('/<a(\s)/i', '<a target="_blank" rel="noopener noreferrer"$1', $content);
                            echo $rendered;
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div class="post-empty">No additional details were provided.</div>
                <?php endif; ?>

                <!-- Link -->
                <?php if ($url !== ''): ?>
                    <div class="post-link-box">
                        <span class="post-link-icon">🔗</span>
                        <div style="flex:1;min-width:0;">
                            <div class="post-link-label">Related Link</div>
                            <a class="post-link-url" href="<?php echo htmlspecialchars($url); ?>"
                               target="_blank" rel="noopener noreferrer">
                                <?php echo htmlspecialchars($url); ?>
                            </a>
                        </div>
                        <a class="btn btn-primary post-link-btn"
                           href="<?php echo htmlspecialchars($url); ?>"
                           target="_blank" rel="noopener noreferrer"
                           style="font-size:12px;padding:8px 16px;">
                            Open →
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="post-footer">
                    <span class="post-footer-date">
                        Posted <?php echo date('M j, Y \a\t g:i A', strtotime($ann['created_at'])); ?>
                    </span>
                    <a href="alumni_announcements.php" class="btn btn-outline" style="font-size:13px;padding:8px 18px;">
                        ← All Announcements
                    </a>
                </div>

            </div>
        </div>

    </main>
</div>
</body>
</html>

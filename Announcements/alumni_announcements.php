<?php
require_once __DIR__ . '/adms_session.php';
adms_session_start_alumni();
include "config.php";

if (!isset($_SESSION['alumni_id'])) {
    header("Location: alumni_login.php");
    exit();
}

$announcements = [];
$check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($check_table && $check_table->num_rows > 0) {
    $has_pinned = false;
    $col_check  = $conn->query("SHOW COLUMNS FROM announcements LIKE 'is_pinned'");
    if ($col_check && $col_check->num_rows > 0) $has_pinned = true;

    $order = $has_pinned ? "ORDER BY is_pinned DESC, created_at DESC" : "ORDER BY created_at DESC";
    $res   = $conn->query("SELECT * FROM announcements $order");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!$has_pinned) $r['is_pinned'] = 0;
            $announcements[] = $r;
        }
    }
}

function categoryBadge($type) {
    $map = [
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
    $key = strtolower(trim($type ?? 'general'));
    $c   = $map[$key] ?? ['bg'=>'#f5f5f5','color'=>'#555','label'=>ucfirst($key),'icon'=>'📌'];
    return "<span class='cat-badge' style='background:{$c['bg']};color:{$c['color']};'>{$c['icon']} {$c['label']}</span>";
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days === 0)  return 'Today';
    if ($diff->days === 1)  return 'Yesterday';
    if ($diff->days < 7)   return $diff->days . ' days ago';
    if ($diff->days < 30)  return (int)($diff->days/7) . ' week' . ((int)($diff->days/7)>1?'s':'') . ' ago';
    if ($diff->days < 365) return (int)($diff->days/30) . ' month' . ((int)($diff->days/30)>1?'s':'') . ' ago';
    return $then->format('M j, Y');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – Alumni Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ann-grid { display:flex; flex-direction:column; gap:14px; max-width:860px; }

        .ann-card {
            background:var(--white); border:1px solid var(--border);
            border-radius:var(--radius-lg); padding:20px 24px;
            box-shadow:0 2px 10px var(--shadow);
            transition:transform .2s, box-shadow .2s, border-color .2s;
            text-decoration:none; color:inherit; display:block;
        }
        .ann-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px var(--shadow); border-color:var(--green); }
        .ann-card.pinned { border-left:4px solid var(--green); background:linear-gradient(135deg,var(--green-light) 0%,var(--white) 55%); }

        /* thumbnail preview */
        .ann-card-inner { display:flex; gap:16px; align-items:flex-start; }
        .ann-thumb { width:80px; height:68px; border-radius:var(--radius-sm); object-fit:cover; flex-shrink:0; border:1px solid var(--border); }
        .ann-card-text { flex:1; min-width:0; }

        .ann-meta { display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap; }
        .cat-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 11px; border-radius:20px; font-size:11px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; }
        .pinned-label { font-size:11px; font-weight:600; color:var(--green-dark); background:var(--green-light); border:1px solid #a5d6a7; border-radius:20px; padding:2px 9px; }
        .ann-date { font-size:12px; color:var(--muted); margin-left:auto; }

        .ann-title { font-family:var(--font-display); font-size:16px; color:var(--text); line-height:1.35; margin-bottom:5px; }
        .ann-preview { font-size:13px; color:var(--muted); line-height:1.6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .ann-footer { display:flex; align-items:center; justify-content:space-between; margin-top:10px; flex-wrap:wrap; gap:8px; }
        .ann-has-link { font-size:11px; color:var(--green); font-weight:600; }
        .ann-read-more { font-size:12px; font-weight:600; color:var(--green); display:inline-flex; align-items:center; gap:4px; }
        .ann-card:hover .ann-read-more { text-decoration:underline; }

        .filter-bar { display:flex; align-items:center; gap:8px; margin-bottom:20px; flex-wrap:wrap; max-width:860px; }
        .filter-btn { padding:7px 15px; border-radius:20px; font-size:12px; font-weight:600; border:1px solid var(--border); background:var(--white); color:var(--muted); cursor:pointer; transition:all .2s; font-family:var(--font-body); }
        .filter-btn:hover  { border-color:var(--green); color:var(--green); }
        .filter-btn.active { background:var(--green); color:var(--white); border-color:var(--green); }
        .ann-count { margin-left:auto; font-size:12px; color:var(--muted); }

        .empty-ann { text-align:center; padding:70px 30px; background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); max-width:860px; box-shadow:0 2px 10px var(--shadow); }
        .empty-ann .empty-icon { font-size:52px; margin-bottom:14px; }
        .empty-ann h3 { font-family:var(--font-display); color:var(--green-dark); margin-bottom:8px; font-size:18px; }
        .empty-ann p { color:var(--muted); font-size:13px; }
    </style>
</head>
<body>
<div class="layout">
    <?php include "alumni_sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Announcements</h1>
                <p class="page-sub">Stay up to date with news from the College of Computer</p>
            </div>
        </div>

        <div class="filter-bar">
            <button class="filter-btn active" onclick="filterAnn('all',this)">📋 All</button>
            <button class="filter-btn" onclick="filterAnn('announcement',this)">📢 Announcements</button>
            <button class="filter-btn" onclick="filterAnn('alert',this)">⚠️ Alerts</button>
            <button class="filter-btn" onclick="filterAnn('job',this)">💼 Jobs</button>
            <button class="filter-btn" onclick="filterAnn('event',this)">🗓 Events</button>
            <span class="ann-count" id="annCount">
                <?php echo count($announcements); ?> announcement<?php echo count($announcements) !== 1 ? 's' : ''; ?>
            </span>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="empty-ann">
                <div class="empty-icon">📢</div>
                <h3>No Announcements Yet</h3>
                <p>Check back later — the college will post updates here.</p>
            </div>
        <?php else: ?>
            <div class="ann-grid" id="annGrid">
                <?php foreach ($announcements as $ann):
                    $type   = strtolower(trim($ann['type'] ?? 'general'));
                    $pinned = !empty($ann['is_pinned']);
                    $preview = strip_tags($ann['content'] ?? '');
                    $preview = mb_strlen($preview) > 120 ? mb_substr($preview, 0, 120) . '…' : $preview;
                ?>
                <a class="ann-card <?php echo $pinned ? 'pinned' : ''; ?>"
                   href="alumni_announcement_view.php?id=<?php echo (int)$ann['id']; ?>"
                   data-type="<?php echo htmlspecialchars($type); ?>">

                    <div class="ann-meta">
                        <?php echo categoryBadge($type); ?>
                        <?php if ($pinned): ?><span class="pinned-label">📌 Pinned</span><?php endif; ?>
                        <span class="ann-date">
                            <?php echo timeAgo($ann['created_at']); ?> &nbsp;·&nbsp;
                            <?php echo date('M j, Y', strtotime($ann['created_at'])); ?>
                        </span>
                    </div>

                    <div class="ann-card-inner">
                        <?php if (!empty($ann['image_path'])): ?>
                            <img class="ann-thumb" src="<?php echo htmlspecialchars($ann['image_path']); ?>" alt="">
                        <?php endif; ?>
                        <div class="ann-card-text">
                            <div class="ann-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                            <?php if ($preview): ?>
                                <div class="ann-preview"><?php echo htmlspecialchars($preview); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ann-footer">
                        <div>
                            <?php if (!empty($ann['url'])): ?>
                                <span class="ann-has-link">🔗 Contains link</span>
                            <?php endif; ?>
                        </div>
                        <div class="ann-read-more">Read more →</div>
                    </div>

                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</div>
<script>
function filterAnn(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cards = document.querySelectorAll('.ann-card');
    let visible = 0;
    cards.forEach(card => {
        const show = type === 'all' || card.dataset.type === type;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('annCount').textContent =
        visible + ' announcement' + (visible !== 1 ? 's' : '');
}
</script>
</body>
</html>

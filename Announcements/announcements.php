<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = intval($_GET['delete']);

    // Fetch image path before deleting
    $img_stmt = $conn->prepare("SELECT image_path FROM announcements WHERE id = ?");
    $img_stmt->bind_param("i", $del_id);
    $img_stmt->execute();
    $img_row = $img_stmt->get_result()->fetch_assoc();
    $img_stmt->close();

    if (!empty($img_row['image_path']) && file_exists($img_row['image_path'])) {
        unlink($img_row['image_path']);
    }

    $del_stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $del_stmt->bind_param("i", $del_id);
    $del_stmt->execute();
    $del_stmt->close();

    header("Location: announcements.php?deleted=1");
    exit();
}

// Filters
$type_filter = $_GET['type'] ?? '';
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if (!empty($type_filter)) {
    $where   .= " AND type = ?";
    $params[] = $type_filter;
    $types   .= "s";
}

$stmt = $conn->prepare("SELECT * FROM announcements $where ORDER BY created_at DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total  = $result->num_rows;
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;
$stmt->close();

$type_labels = [
    'job'          => ['label' => 'Job',                'icon' => '💼', 'color' => '#1565c0', 'bg' => '#e3f2fd'],
    'event'        => ['label' => 'Event',              'icon' => '📅', 'color' => '#6a1b9a', 'bg' => '#f3e5f5'],
    'announcement' => ['label' => 'Announcement',       'icon' => '📢', 'color' => '#2e7d32', 'bg' => '#e8f5e9'],
    'alert'        => ['label' => 'Alert',              'icon' => '🚨', 'color' => '#b71c1c', 'bg' => '#ffebee'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – ADMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ann-toolbar { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
        .type-tabs { display:flex; gap:8px; flex-wrap:wrap; }
        .type-tab { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:20px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid var(--border); background:var(--white); color:var(--muted); text-decoration:none; transition:all .2s; }
        .type-tab:hover { border-color:var(--green); color:var(--green-dark); }
        .type-tab.active { background:var(--green); color:var(--white); border-color:var(--green); }
        .type-tab .tab-count { background:rgba(255,255,255,0.3); border-radius:10px; padding:1px 6px; font-size:10px; }
        .type-tab:not(.active) .tab-count { background:var(--green-light); color:var(--green-dark); }

        .ann-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:18px; }
        .ann-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; box-shadow:0 2px 10px var(--shadow); display:flex; flex-direction:column; transition:transform .2s, box-shadow .2s; }
        .ann-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px var(--shadow); }
        .ann-card-img { width:100%; height:160px; object-fit:cover; border-bottom:1px solid var(--border); background:var(--off-white); display:flex; align-items:center; justify-content:center; font-size:48px; color:var(--border); }
        .ann-card-img img { width:100%; height:100%; object-fit:cover; }
        .ann-card-body { padding:16px; flex:1; display:flex; flex-direction:column; gap:8px; }
        .ann-type-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; letter-spacing:.5px; width:fit-content; }
        .ann-title { font-size:15px; font-weight:600; color:var(--text); line-height:1.4; }
        .ann-content { font-size:12.5px; color:var(--muted); line-height:1.6; flex:1; overflow:hidden; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; }
        .ann-url { display:inline-flex; align-items:center; gap:5px; font-size:12px; color:var(--green); font-weight:500; word-break:break-all; }
        .ann-url:hover { text-decoration:underline; }
        .ann-meta { font-size:11px; color:var(--muted); border-top:1px solid var(--border); padding-top:10px; margin-top:4px; display:flex; justify-content:space-between; align-items:center; }
        .ann-actions { display:flex; gap:8px; }

        .empty-state { text-align:center; padding:60px 20px; color:var(--muted); background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); }
        .empty-state .empty-icon { font-size:48px; margin-bottom:14px; }
    </style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Announcements & Events</h1>
                <p class="page-sub">Manage posts visible to alumni</p>
            </div>
            <a class="btn btn-primary" href="add_announcement.php">➕ New Post</a>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">✅ Post deleted successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">✅ Post saved successfully.</div>
        <?php endif; ?>

        <!-- Type Tabs -->
        <div class="ann-toolbar">
            <div class="type-tabs">
                <?php
                // Count per type
                $counts_res = $conn->query("SELECT type, COUNT(*) as c FROM announcements GROUP BY type");
                $counts = [];
                while ($cr = $counts_res->fetch_assoc()) $counts[$cr['type']] = $cr['c'];
                $total_all = array_sum($counts);
                ?>
                <a href="announcements.php" class="type-tab <?php echo empty($type_filter) ? 'active' : ''; ?>">
                    All <span class="tab-count"><?php echo $total_all; ?></span>
                </a>
                <?php foreach ($type_labels as $key => $meta): ?>
                <a href="announcements.php?type=<?php echo $key; ?>"
                   class="type-tab <?php echo $type_filter === $key ? 'active' : ''; ?>">
                    <?php echo $meta['icon']; ?> <?php echo $meta['label']; ?>
                    <span class="tab-count"><?php echo $counts[$key] ?? 0; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <span style="font-size:13px; color:var(--muted);">
                Showing <strong><?php echo $total; ?></strong> post(s)
            </span>
        </div>

        <!-- Cards Grid -->
        <?php if (empty($rows)): ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <p>No posts found. <a href="add_announcement.php" style="color:var(--green);">Create the first one →</a></p>
        </div>
        <?php else: ?>
        <div class="ann-grid">
            <?php foreach ($rows as $row):
                $tm = $type_labels[$row['type']] ?? $type_labels['announcement'];
            ?>
            <div class="ann-card">
                <div class="ann-card-img">
                    <?php if (!empty($row['image_path']) && file_exists($row['image_path'])): ?>
                        <a href="<?php echo htmlspecialchars($row['image_path']); ?>" target="_blank" rel="noopener noreferrer" title="Click to open full image" style="display:block;width:100%;height:100%;">
                            <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Post image" style="cursor:zoom-in;">
                        </a>
                    <?php else: ?>
                        <?php echo $tm['icon']; ?>
                    <?php endif; ?>
                </div>
                <div class="ann-card-body">
                    <span class="ann-type-badge"
                          style="background:<?php echo $tm['bg']; ?>; color:<?php echo $tm['color']; ?>;">
                        <?php echo $tm['icon']; ?> <?php echo $tm['label']; ?>
                    </span>
                    <div class="ann-title"><?php echo htmlspecialchars($row['title']); ?></div>
                    <?php if (!empty($row['content'])): ?>
                        <div class="ann-content"><?php echo htmlspecialchars($row['content']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($row['url'])): ?>
                        <a href="<?php echo htmlspecialchars($row['url']); ?>" target="_blank" class="ann-url">
                            🔗 <?php echo htmlspecialchars($row['url']); ?>
                        </a>
                    <?php endif; ?>
                    <div class="ann-meta">
                        <span><?php echo date('M j, Y  H:i', strtotime($row['created_at'])); ?></span>
                        <div class="ann-actions">
                            <a class="btn btn-success" style="padding:5px 12px;font-size:12px;"
                               href="edit_announcement.php?id=<?php echo $row['id']; ?>">✏️ Edit</a>
                            <a class="btn btn-danger" style="padding:5px 12px;font-size:12px;"
                               href="announcements.php?delete=<?php echo $row['id']; ?>"
                               onclick="return confirm('Delete this post permanently?')">🗑️ Delete</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>

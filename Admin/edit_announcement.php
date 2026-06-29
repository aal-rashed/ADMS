<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: announcements.php");
    exit();
}

$id   = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) { header("Location: announcements.php"); exit(); }
$post = $result->fetch_assoc();
$stmt->close();

$message  = "";
$msg_type = "";

$upload_dir = "uploads/announcements/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title   = trim($_POST['title'] ?? '');
    $type    = trim($_POST['type'] ?? 'announcement');
    $content = trim($_POST['content'] ?? '');
    $url     = trim($_POST['url'] ?? '');

    $allowed_types = ['job','event','announcement','alert','link'];
    if (!in_array($type, $allowed_types)) $type = 'announcement';

    if (empty($title)) {
        $message  = "Title is required.";
        $msg_type = "error";
    } else {
        $image_path = $post['image_path']; // Keep existing by default

        // Remove existing image if requested
        if (isset($_POST['remove_image']) && !empty($post['image_path'])) {
            if (file_exists($post['image_path'])) unlink($post['image_path']);
            $image_path = null;
        }

        // Handle new image upload
        if (!empty($_FILES['image']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed_exts)) {
                $message  = "Invalid image type.";
                $msg_type = "error";
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $message  = "Image too large. Max 5MB.";
                $msg_type = "error";
            } else {
                // Remove old image
                if (!empty($post['image_path']) && file_exists($post['image_path'])) {
                    unlink($post['image_path']);
                }
                $fname      = uniqid('ann_') . '.' . $ext;
                $image_path = $upload_dir . $fname;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                    $message  = "Failed to upload image.";
                    $msg_type = "error";
                    $image_path = $post['image_path']; // revert
                }
            }
        }

        if (!$message) {
            $upd = $conn->prepare("UPDATE announcements SET type=?, title=?, content=?, image_path=?, url=? WHERE id=?");
            $upd->bind_param("sssssi", $type, $title, $content, $image_path, $url, $id);
            if ($upd->execute()) {
                $post['type']       = $type;
                $post['title']      = $title;
                $post['content']    = $content;
                $post['image_path'] = $image_path;
                $post['url']        = $url;
                $message  = "Post updated successfully!";
                $msg_type = "success";
            } else {
                $message  = "Update failed: " . $conn->error;
                $msg_type = "error";
            }
            $upd->close();
        }
    }
}

$type_options = [
    'job'          => ['label' => '💼 Job Opportunity',    'desc' => 'Career and hiring announcements'],
    'event'        => ['label' => '📅 Event',              'desc' => 'Upcoming activities or gatherings'],
    'announcement' => ['label' => '📢 General Announcement','desc' => 'General news or updates'],
    'alert'        => ['label' => '🚨 Alert',              'desc' => 'Urgent or time-sensitive notices'],
    'link'         => ['label' => '🔗 Link / Resource',    'desc' => 'Useful external links or tools'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post – ADMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .post-form-wrap { display:grid; grid-template-columns:1fr 340px; gap:24px; align-items:start; }
        @media(max-width:900px) { .post-form-wrap { grid-template-columns:1fr; } }
        .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; box-shadow:0 2px 10px var(--shadow); }
        .form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--green); margin:24px 0 14px; padding-bottom:6px; border-bottom:2px solid var(--green-light); }
        .form-section-title:first-child { margin-top:0; }
        .field { display:flex; flex-direction:column; gap:5px; margin-bottom:16px; }
        .field label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.9px; color:var(--muted); }
        .field input, .field select, .field textarea { background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:9px 12px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; transition:border-color .2s; width:100%; }
        .field input:focus, .field select:focus, .field textarea:focus { border-color:var(--green); background:var(--white); }
        .field textarea { resize:vertical; min-height:130px; line-height:1.7; }
        .type-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .type-option { position:relative; }
        .type-option input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
        .type-option label { display:flex; align-items:flex-start; gap:10px; padding:12px; border:2px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; transition:all .2s; background:var(--off-white); }
        .type-option label:hover { border-color:var(--green); background:var(--green-light); }
        .type-option input[type=radio]:checked + label { border-color:var(--green); background:var(--green-light); }
        .type-option .t-icon { font-size:20px; flex-shrink:0; margin-top:1px; }
        .type-option .t-name { font-size:12.5px; font-weight:600; color:var(--text); }
        .type-option .t-desc { font-size:11px; color:var(--muted); margin-top:2px; }
        .img-drop { border:2px dashed var(--border); border-radius:var(--radius-md); padding:28px 16px; text-align:center; cursor:pointer; transition:all .3s; background:var(--off-white); }
        .img-drop:hover, .img-drop.has-file { border-color:var(--green); background:var(--green-light); }
        .img-drop .dz-icon { font-size:32px; margin-bottom:8px; }
        .img-drop .dz-title { font-size:13px; font-weight:600; color:var(--text); margin-bottom:4px; }
        .img-drop .dz-sub { font-size:11px; color:var(--muted); }
        .img-drop .dz-file { font-size:12px; color:var(--green-dark); font-weight:600; margin-top:8px; }
        #imgPreview { width:100%; border-radius:var(--radius-sm); margin-top:12px; max-height:200px; object-fit:cover; }
        .current-img-wrap { margin-bottom:12px; }
        .current-img-wrap img { width:100%; border-radius:var(--radius-sm); max-height:160px; object-fit:cover; border:1px solid var(--border); }
        .remove-img-btn { display:inline-flex; align-items:center; gap:5px; font-size:12px; color:var(--red); cursor:pointer; margin-top:6px; }
        .remove-img-btn input { display:none; }
        .sidebar-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; box-shadow:0 2px 10px var(--shadow); margin-bottom:16px; }
        .sidebar-card h3 { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--green); margin-bottom:14px; }
    </style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Edit Post</h1>
                <p class="page-sub">
                    <a href="announcements.php" style="color:var(--green);">Announcements</a> /
                    <?php echo htmlspecialchars($post['title']); ?>
                </p>
            </div>
            <a class="btn btn-outline" href="announcements.php">← Back</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
        <div class="post-form-wrap">

            <div>
                <div class="form-card">
                    <div class="form-section-title">📝 Content</div>

                    <div class="field">
                        <label>Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                    </div>

                    <div class="field">
                        <label>Body Text</label>
                        <textarea name="content"><?php echo htmlspecialchars($post['content'] ?? ''); ?></textarea>
                    </div>

                    <div class="field">
                        <label>URL / Link (optional)</label>
                        <input type="url" name="url" value="<?php echo htmlspecialchars($post['url'] ?? ''); ?>">
                    </div>

                    <div class="form-section-title">📂 Post Type</div>
                    <div class="type-grid">
                        <?php foreach ($type_options as $key => $opt):
                            $checked = ($post['type'] === $key) ? 'checked' : '';
                        ?>
                        <div class="type-option">
                            <input type="radio" name="type" id="type_<?php echo $key; ?>"
                                   value="<?php echo $key; ?>" <?php echo $checked; ?>>
                            <label for="type_<?php echo $key; ?>">
                                <span class="t-icon"><?php echo explode(' ', $opt['label'])[0]; ?></span>
                                <div>
                                    <div class="t-name"><?php echo ltrim(substr($opt['label'], strpos($opt['label'], ' '))); ?></div>
                                    <div class="t-desc"><?php echo $opt['desc']; ?></div>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="sidebar-card">
                    <h3>🖼️ Featured Image</h3>

                    <?php if (!empty($post['image_path']) && file_exists($post['image_path'])): ?>
                    <div class="current-img-wrap" id="currentImgWrap">
                        <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Current image">
                        <label class="remove-img-btn">
                            <input type="checkbox" name="remove_image" id="removeImg">
                            🗑️ Remove current image
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="img-drop" id="imgDrop" onclick="document.getElementById('imgInput').click()">
                        <div class="dz-icon">📷</div>
                        <div class="dz-title">Upload new image</div>
                        <div class="dz-sub">JPG, PNG, GIF, WEBP — max 5MB</div>
                        <div class="dz-file" id="imgFileName"></div>
                    </div>
                    <input type="file" name="image" id="imgInput" accept="image/*" style="display:none;">
                    <img id="imgPreview" src="" alt="Preview" style="display:none;">
                </div>

                <div class="sidebar-card">
                    <h3>⚡ Actions</h3>
                    <button type="submit" class="btn btn-primary"
                            style="width:100%;justify-content:center;padding:12px;font-size:14px;margin-bottom:10px;">
                        💾 Save Changes
                    </button>
                    <a href="announcements.php?delete=<?php echo $id; ?>"
                       class="btn btn-danger"
                       style="width:100%;justify-content:center;padding:11px;font-size:13px;"
                       onclick="return confirm('Delete this post permanently?')">
                        🗑️ Delete Post
                    </a>
                </div>

                <div class="sidebar-card" style="font-size:12px; color:var(--muted); line-height:1.8;">
                    <h3>ℹ️ Details</h3>
                    Created: <?php echo date('M j, Y  H:i', strtotime($post['created_at'])); ?><br>
                    Updated: <?php echo date('M j, Y  H:i', strtotime($post['updated_at'])); ?>
                </div>
            </div>

        </div>
        </form>

    </main>
</div>

<script>
const imgInput    = document.getElementById('imgInput');
const imgDrop     = document.getElementById('imgDrop');
const imgFileName = document.getElementById('imgFileName');
const imgPreview  = document.getElementById('imgPreview');

imgInput.addEventListener('change', () => {
    const file = imgInput.files[0];
    if (!file) return;
    imgFileName.textContent = '📎 ' + file.name;
    imgDrop.classList.add('has-file');
    const reader = new FileReader();
    reader.onload = e => { imgPreview.src = e.target.result; imgPreview.style.display = 'block'; };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>

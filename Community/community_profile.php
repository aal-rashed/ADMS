<?php
/**
 * Community Profile Page — supports BOTH admin and alumni profiles.
 *   URL: community_profile.php?type=admin|alumni&id={user_id}
 *   Backward compat: ?id={alumni_id} (no type) defaults to alumni
 */
require_once __DIR__ . '/adms_session.php';
$admsCommunityPortal = adms_session_start_community($_GET['portal'] ?? null);
require __DIR__ . '/config.php';
require __DIR__ . '/community_lib.php';

$viewer = community_require_viewer();
$csrf   = community_ensure_csrf();

/* ------------------------------------------------------------------
 * Resolve profile target (type + id)
 * ------------------------------------------------------------------ */
$profileType = isset($_GET['type']) && in_array($_GET['type'], ['admin', 'alumni'], true)
    ? $_GET['type']
    : 'alumni';
$profileId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($profileId <= 0) {
    if ($viewer['type'] === 'admin')  { $profileType = 'admin';  $profileId = (int) $viewer['admin_id']; }
    if ($viewer['type'] === 'alumni') { $profileType = 'alumni'; $profileId = (int) $viewer['alumni_id']; }
}
if ($profileId <= 0) {
    header('Location: community.php' . ($admsCommunityPortal ? '?portal=' . urlencode($admsCommunityPortal) : ''));
    exit;
}

/* ------------------------------------------------------------------
 * Load user record
 * ------------------------------------------------------------------ */
$userRow = null;
if ($pdo) {
    if ($profileType === 'admin') {
        try {
            $st = $pdo->prepare('SELECT id, name, username, profile_photo, bio FROM admins WHERE id = ?');
            $st->execute([$profileId]);
        } catch (Throwable $e) {
            $st = $pdo->prepare('SELECT id, name, username FROM admins WHERE id = ?');
            $st->execute([$profileId]);
        }
    } else {
        $st = $pdo->prepare('SELECT id, name, student_id, profile_photo, bio FROM alumni WHERE id = ?');
        $st->execute([$profileId]);
    }
    $userRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$userRow) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px"><h2>Profile not found</h2><a href="community.php' . htmlspecialchars($admsCommunityPortal ? '?portal=' . $admsCommunityPortal : '') . '">← Back to Community</a></body></html>';
    exit;
}

$displayName   = $userRow['name'] ?? '';
$displayHandle = $profileType === 'admin' ? ($userRow['username'] ?? '—') : ($userRow['student_id'] ?? '—');
$displayPhoto  = $userRow['profile_photo'] ?? null;
$displayBio    = $userRow['bio'] ?? '';

$isOwnProfile = false;
if ($profileType === 'admin'  && $viewer['type'] === 'admin'  && (int) $viewer['admin_id']  === (int) $userRow['id']) $isOwnProfile = true;
if ($profileType === 'alumni' && $viewer['type'] === 'alumni' && (int) $viewer['alumni_id'] === (int) $userRow['id']) $isOwnProfile = true;

/* ------------------------------------------------------------------
 * Load this user's posts with engagement meta
 * ------------------------------------------------------------------ */
$ready    = $pdo && community_tables_ready($pdo);
$postRows = [];
$meta     = ['like_counts' => [], 'comment_counts' => [], 'repost_counts' => [], 'liked' => [], 'reposted' => []];

if ($ready) {
    if ($profileType === 'admin') {
        $st = $pdo->prepare('SELECT * FROM community_posts WHERE author_type = ? AND author_admin_id = ? ORDER BY created_at DESC LIMIT 60');
        $st->execute(['admin', $profileId]);
    } else {
        $st = $pdo->prepare('SELECT * FROM community_posts WHERE author_type = ? AND author_alumni_id = ? ORDER BY created_at DESC LIMIT 60');
        $st->execute(['alumni', $profileId]);
    }
    $postRows = $st->fetchAll(PDO::FETCH_ASSOC);

    $postIds = array_map(fn($p) => (int) $p['id'], $postRows);

    if ($postIds) {
        $ph = implode(',', array_fill(0, count($postIds), '?'));

        $st = $pdo->prepare("SELECT post_id, COUNT(*) AS c FROM community_likes WHERE post_id IN ($ph) GROUP BY post_id");
        $st->execute($postIds);
        while ($r = $st->fetch()) { $meta['like_counts'][(int)$r['post_id']] = (int)$r['c']; }

        $st = $pdo->prepare("SELECT post_id, COUNT(*) AS c FROM community_comments WHERE post_id IN ($ph) GROUP BY post_id");
        $st->execute($postIds);
        while ($r = $st->fetch()) { $meta['comment_counts'][(int)$r['post_id']] = (int)$r['c']; }

        $st = $pdo->prepare("SELECT original_post_id, COUNT(*) AS c FROM community_reposts WHERE original_post_id IN ($ph) GROUP BY original_post_id");
        $st->execute($postIds);
        while ($r = $st->fetch()) { $meta['repost_counts'][(int)$r['original_post_id']] = (int)$r['c']; }

        if ($viewer['type'] === 'admin' && $viewer['admin_id']) {
            $st = $pdo->prepare("SELECT post_id FROM community_likes WHERE post_id IN ($ph) AND liker_type='admin' AND liker_admin_id = ?");
            $st->execute(array_merge($postIds, [(int)$viewer['admin_id']]));
        } else {
            $st = $pdo->prepare("SELECT post_id FROM community_likes WHERE post_id IN ($ph) AND liker_type='alumni' AND liker_alumni_id = ?");
            $st->execute(array_merge($postIds, [(int)$viewer['alumni_id']]));
        }
        while ($r = $st->fetch()) { $meta['liked'][(int)$r['post_id']] = true; }

        if ($viewer['type'] === 'admin' && $viewer['admin_id']) {
            $st = $pdo->prepare("SELECT original_post_id FROM community_reposts WHERE original_post_id IN ($ph) AND reposter_type='admin' AND reposter_admin_id = ?");
            $st->execute(array_merge($postIds, [(int)$viewer['admin_id']]));
        } else {
            $st = $pdo->prepare("SELECT original_post_id FROM community_reposts WHERE original_post_id IN ($ph) AND reposter_type='alumni' AND reposter_alumni_id = ?");
            $st->execute(array_merge($postIds, [(int)$viewer['alumni_id']]));
        }
        while ($r = $st->fetch()) { $meta['reposted'][(int)$r['original_post_id']] = true; }
    }
}

$portalQ = $admsCommunityPortal ? '&portal=' . urlencode($admsCommunityPortal) : '';

/* Big avatar helper */
function profile_big_avatar(?string $photoPath, string $name, string $size = '96px'): string
{
    if ($photoPath && file_exists(__DIR__ . '/' . $photoPath)) {
        $url = htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $url . '" alt="" class="prof-avatar-img" style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;flex-shrink:0;">';
    }
    $parts = preg_split('/\s+/', trim($name)) ?: ['?'];
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    $colors = ['#2e7d32','#1565c0','#6a1b9a','#e65100','#37474f'];
    $color  = $colors[abs(crc32($name)) % count($colors)];
    $fs     = (intval($size) < 40) ? '13px' : (intval($size) < 80 ? '20px' : '30px');
    return '<div class="prof-avatar-init" style="width:' . $size . ';height:' . $size . ';border-radius:50%;background:' . $color . ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:' . $fs . ';font-weight:700;flex-shrink:0;">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo community_h($displayName); ?> – Community – ADMS</title>
    <meta name="adms-community-portal" content="<?php echo ($admsCommunityPortal === 'admin' || $admsCommunityPortal === 'alumni') ? $admsCommunityPortal : ''; ?>">
    <link rel="stylesheet" href="style.css">
    <style>
        .com-wrap        { max-width: 720px; }
        .com-muted       { color: var(--muted); font-size: 12px; margin-left: 6px; }
        .com-badge       { display:inline-block; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:2px 8px; border-radius:20px; vertical-align:middle; }
        .com-badge-admin { background:#e3f2fd; color:#1565c0; border:1px solid #90caf9; }
        .com-badge-alumni{ background:var(--green-light); color:var(--green-dark); border:1px solid #a5d6a7; }
        .com-card        { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px 20px; box-shadow:0 2px 10px var(--shadow); }
        .com-card-head   { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:10px; }
        .com-card-author strong { font-size:14px; }
        .com-card-body .com-text { font-size:14px; line-height:1.65; margin-bottom:10px; word-break:break-word; }
        .com-img-wrap    { margin:10px 0; border-radius:var(--radius-md); overflow:hidden; border:1px solid var(--border); max-height:360px; }
        .com-img-wrap img{ width:100%; height:auto; display:block; object-fit:cover; }
        .com-link-chip   { display:inline-flex; align-items:center; gap:6px; margin-top:6px; padding:7px 12px; border-radius:var(--radius-sm); background:var(--green-light); color:var(--green-dark); font-size:12px; font-weight:600; border:1px solid #a5d6a7; word-break:break-all; }
        .com-card-foot   { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:12px; padding-top:12px; border-top:1px solid var(--border); }
        .com-feed        { display:flex; flex-direction:column; gap:16px; }

        .prof-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:32px 28px 24px; box-shadow:0 2px 14px var(--shadow); margin-bottom:28px; position:relative; }
        .prof-header { display:flex; align-items:flex-start; gap:22px; flex-wrap:wrap; }
        .prof-photo-wrap { position:relative; flex-shrink:0; }
        .prof-photo-wrap .prof-avatar-img, .prof-photo-wrap .prof-avatar-init {
            width:96px !important; height:96px !important; font-size:30px !important;
            border:3px solid var(--green-light); box-shadow:0 2px 10px var(--shadow);
        }
        .prof-photo-edit-btn { position:absolute; bottom:2px; right:2px; width:28px; height:28px; border-radius:50%; background:var(--green); color:#fff; border:2px solid #fff; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s; }
        .prof-photo-edit-btn:hover { background:var(--green-dark); }
        .prof-meta { flex:1; min-width:0; }
        .prof-name { font-family:var(--font-display); font-size:22px; color:var(--green-dark); line-height:1.2; margin-bottom:4px; }
        .prof-username { font-size:14px; color:var(--muted); font-weight:500; margin-bottom:10px; }
        .prof-bio { font-size:14px; color:var(--text); line-height:1.65; white-space:pre-wrap; word-break:break-word; }
        .prof-bio-empty { font-size:13px; color:var(--muted); font-style:italic; }
        .prof-edit-btn { position:absolute; top:20px; right:20px; display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:var(--radius-sm); background:var(--green-light); color:var(--green-dark); border:1px solid #a5d6a7; font-size:13px; font-weight:600; cursor:pointer; transition:background .2s; }
        .prof-edit-btn:hover { background:#c8e6c9; }
        .prof-posts-title { font-family:var(--font-display); font-size:15px; color:var(--green-dark); margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }

        .prof-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:500; align-items:center; justify-content:center; }
        .prof-modal-backdrop.open { display:flex; }
        .prof-modal { background:var(--white); border-radius:var(--radius-lg); padding:28px 26px 22px; width:min(480px,94vw); box-shadow:0 8px 40px rgba(0,0,0,.22); position:relative; }
        .prof-modal h3 { font-family:var(--font-display); font-size:18px; color:var(--green-dark); margin-bottom:18px; }
        .prof-modal-close { position:absolute; top:14px; right:16px; background:none; border:none; font-size:20px; cursor:pointer; color:var(--muted); }
        .prof-modal .field { margin-bottom:14px; }
        .prof-modal .field label { display:block; font-size:13px; font-weight:600; color:var(--text); margin-bottom:5px; }
        .prof-modal .field input[type="file"], .prof-modal .field textarea { width:100%; }
        .prof-modal .prof-preview-wrap { display:flex; align-items:center; gap:14px; margin-bottom:16px; }
        .prof-msg { font-size:13px; margin-top:10px; }
        .prof-msg.ok { color:var(--green-dark); }
        .prof-msg.err { color:var(--red); }

        .back-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--green-dark); font-weight:500; margin-bottom:18px; }
        .back-link:hover { text-decoration:underline; }
        .role-badge { display:inline-block; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:3px 9px; border-radius:20px; margin-left:8px; vertical-align:middle; }
        .role-badge.role-admin  { background:#e3f2fd; color:#1565c0; border:1px solid #90caf9; }
        .role-badge.role-alumni { background:var(--green-light); color:var(--green-dark); border:1px solid #a5d6a7; }
    </style>
</head>
<body>
<div class="layout">
    <?php
    if (($viewer['type'] ?? '') === 'admin') {
        include __DIR__ . '/sidebar.php';
    } else {
        include __DIR__ . '/alumni_sidebar.php';
    }
    ?>
    <main class="main-content animate-fadeIn">

        <a class="back-link" href="community.php<?php echo htmlspecialchars($admsCommunityPortal ? '?portal=' . $admsCommunityPortal : ''); ?>">
            ← Back to Community
        </a>

        <?php if (!$pdo): ?>
            <div class="alert alert-error">Database unavailable.</div>
        <?php elseif (!$ready): ?>
            <div class="alert alert-warning">Community tables not installed.</div>
        <?php else: ?>

        <div class="com-wrap">

            <!-- ═══════════════ PROFILE CARD ═══════════════ -->
            <div class="prof-card">

                <?php if ($isOwnProfile): ?>
                    <button class="prof-edit-btn" id="profEditBtn" type="button">✏️ Edit profile</button>
                <?php endif; ?>

                <div class="prof-header">
                    <div class="prof-photo-wrap" id="profPhotoWrap">
                        <?php echo profile_big_avatar($displayPhoto, (string)$displayName, '96px'); ?>
                        <?php if ($isOwnProfile): ?>
                            <button class="prof-photo-edit-btn" id="profPhotoTrigger" title="Change photo" type="button">📷</button>
                        <?php endif; ?>
                    </div>

                    <div class="prof-meta">
                        <div class="prof-name">
                            <?php echo community_h($displayName); ?>
                            <span class="role-badge <?php echo $profileType === 'admin' ? 'role-admin' : 'role-alumni'; ?>">
                                <?php echo $profileType === 'admin' ? 'Staff' : 'Alumni'; ?>
                            </span>
                        </div>
                        <div class="prof-username">@<?php echo community_h($displayHandle); ?></div>
                        <div id="profBioDisplay">
                        <?php if (!empty($displayBio)): ?>
                            <div class="prof-bio"><?php echo nl2br(community_h($displayBio)); ?></div>
                        <?php else: ?>
                            <div class="prof-bio-empty"><?php echo $isOwnProfile ? 'No bio yet — click "Edit profile" to add one.' : 'No bio yet.'; ?></div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════ POSTS ═══════════════ -->
            <div class="prof-posts-title">
                📝 Posts <span style="font-family:var(--font-body);font-size:13px;font-weight:400;color:var(--muted);">(<?php echo count($postRows); ?>)</span>
            </div>

            <div class="com-feed" id="comFeed">
            <?php if (!$postRows): ?>
                <div class="com-card" style="padding:40px;text-align:center;color:var(--muted);">
                    <?php echo $isOwnProfile ? "You haven't posted anything yet." : "No posts yet."; ?>
                </div>
            <?php else:
                foreach ($postRows as $p):
                    $pid      = (int) $p['id'];
                    $likes    = $meta['like_counts'][$pid]    ?? 0;
                    $commCnt  = $meta['comment_counts'][$pid] ?? 0;
                    $reposts  = $meta['repost_counts'][$pid]  ?? 0;
                    $liked    = !empty($meta['liked'][$pid]);
                    $reposted = !empty($meta['reposted'][$pid]);
                    $canDel   = community_can_delete_post($viewer, $p);
                    $likeClass = $liked    ? 'btn btn-primary com-like-btn'   : 'btn btn-outline com-like-btn';
                    $repClass  = $reposted ? 'btn btn-primary com-repost-btn' : 'btn btn-outline com-repost-btn';
                    $badgeHtml = $profileType === 'admin'
                        ? '<span class="com-badge com-badge-admin">Staff</span>'
                        : '<span class="com-badge com-badge-alumni">Alumni</span>';
                    $postLink = 'community_post.php?id=' . $pid . $portalQ;
            ?>
                <article class="com-card" data-post-id="<?php echo $pid; ?>" data-feed-kind="post">
                    <header class="com-card-head">
                        <div class="com-card-author" style="display:flex;align-items:center;gap:10px;">
                            <?php echo community_avatar_html($displayPhoto, $displayName, '36px'); ?>
                            <div>
                                <strong><?php echo community_h($displayName); ?></strong>
                                <?php echo $badgeHtml; ?>
                                <span class="com-muted">· <?php echo community_h(community_format_time($p['created_at'])); ?></span>
                            </div>
                        </div>
                        <?php if ($canDel): ?>
                            <button type="button" class="btn btn-danger com-del-post" style="padding:5px 12px;font-size:11px;">Delete</button>
                        <?php endif; ?>
                    </header>

                    <div class="com-card-body">
                        <?php if ($p['body']): ?>
                            <a href="<?php echo htmlspecialchars($postLink); ?>" style="color:inherit;text-decoration:none;display:block;">
                                <p class="com-text"><?php echo nl2br(community_h($p['body'])); ?></p>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($p['image_path'])): ?>
                            <a href="<?php echo htmlspecialchars($postLink); ?>" style="display:block;">
                                <div class="com-img-wrap"><img src="<?php echo community_h($p['image_path']); ?>" alt=""></div>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($p['link_url'])): ?>
                            <a class="com-link-chip" href="<?php echo community_h($p['link_url']); ?>" target="_blank" rel="noopener noreferrer">
                                🔗 <?php echo community_h($p['link_url']); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <footer class="com-card-foot">
                        <button type="button" class="<?php echo $likeClass; ?>" data-liked="<?php echo $liked ? '1' : '0'; ?>">
                            ❤️ Like <span class="com-like-count"><?php echo $likes; ?></span>
                        </button>
                        <button type="button" class="<?php echo $repClass; ?>" data-reposted="<?php echo $reposted ? '1' : '0'; ?>">
                            🔁 Repost <span class="com-repost-count"><?php echo $reposts; ?></span>
                        </button>
                        <a class="com-comments-link" href="<?php echo htmlspecialchars($postLink); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--radius-sm);background:var(--off-white);border:1px solid var(--border);color:var(--text);font-size:13px;font-weight:500;text-decoration:none;transition:background .2s;">
                            💬 <?php echo $commCnt; ?> comment<?php echo $commCnt === 1 ? '' : 's'; ?>
                        </a>
                    </footer>
                </article>
            <?php endforeach; endif; ?>
            </div>

        </div>

        <?php if ($isOwnProfile): ?>
        <!-- ═══════════════ EDIT PROFILE MODAL ═══════════════ -->
        <div class="prof-modal-backdrop" id="profModalBackdrop">
            <div class="prof-modal" role="dialog" aria-modal="true" aria-labelledby="profModalTitle">
                <button class="prof-modal-close" id="profModalClose" aria-label="Close">✕</button>
                <h3 id="profModalTitle">Edit your profile</h3>

                <form id="profEditForm" enctype="multipart/form-data">
                    <input type="hidden" name="action"  value="update_profile">
                    <input type="hidden" name="csrf"    value="<?php echo community_h($csrf); ?>">
                    <input type="hidden" name="portal"  value="<?php echo community_h($admsCommunityPortal === 'admin' || $admsCommunityPortal === 'alumni' ? $admsCommunityPortal : ''); ?>">
                    <input type="hidden" name="role"    value="<?php echo community_h($profileType); ?>">

                    <div class="prof-preview-wrap">
                        <div id="profModalAvatarWrap">
                            <?php echo profile_big_avatar($displayPhoto, (string)$displayName, '72px'); ?>
                        </div>
                        <div>
                            <div class="field" style="margin-bottom:4px;">
                                <label for="profPhotoInput">Profile photo</label>
                                <input id="profPhotoInput" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.gif,.webp">
                            </div>
                            <div style="font-size:11px;color:var(--muted);">JPG, PNG, WEBP or GIF · max 4 MB</div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="profBioInput">Bio <span style="font-weight:400;color:var(--muted);">(max 500 chars)</span></label>
                        <textarea id="profBioInput" name="bio" rows="4" maxlength="500" placeholder="Tell the community a bit about yourself…"><?php echo community_h($displayBio); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="profSaveBtn">Save changes</button>
                    <span class="prof-msg" id="profSaveMsg"></span>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <script>
        (function () {
            const CSRF   = <?php echo json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const PORTAL = document.querySelector('meta[name="adms-community-portal"]')?.getAttribute('content') || '';

            async function api(action, payload) {
                const body = Object.assign({ action, csrf: CSRF }, PORTAL ? { portal: PORTAL } : {}, payload || {});
                const res = await fetch('community_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', ...(PORTAL ? { 'X-ADMS-PORTAL': PORTAL } : {}) },
                    body: JSON.stringify(body),
                });
                let data;
                try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Bad response' }; }
                if (!data.ok) throw new Error(data.error || 'Request failed');
                return data;
            }
            function postCard(el) { return el.closest('.com-card[data-post-id]'); }

            document.getElementById('comFeed')?.addEventListener('click', async function (e) {
                const t = e.target;
                if (t.classList.contains('com-like-btn')) {
                    const card = postCard(t); const postId = parseInt(card?.dataset.postId || '0', 10);
                    if (!postId) return; t.disabled = true;
                    try {
                        const data = await api('toggle_like', { post_id: postId });
                        const c = card.querySelector('.com-like-count'); if (c) c.textContent = data.like_count;
                        t.dataset.liked = data.liked ? '1' : '0';
                        t.classList.toggle('btn-primary', data.liked); t.classList.toggle('btn-outline', !data.liked);
                    } catch (err) { alert(err.message); }
                    t.disabled = false; return;
                }
                if (t.classList.contains('com-repost-btn')) {
                    const card = postCard(t); const postId = parseInt(card?.dataset.postId || '0', 10);
                    if (!postId) return; t.disabled = true;
                    try {
                        const data = await api('toggle_repost', { post_id: postId });
                        const c = card.querySelector('.com-repost-count'); if (c) c.textContent = data.repost_count;
                        t.dataset.reposted = data.reposted ? '1' : '0';
                        t.classList.toggle('btn-primary', data.reposted); t.classList.toggle('btn-outline', !data.reposted);
                    } catch (err) { alert(err.message); }
                    t.disabled = false; return;
                }
                if (t.classList.contains('com-del-post')) {
                    const card = postCard(t); const postId = parseInt(card?.dataset.postId || '0', 10);
                    if (!postId || !confirm('Delete this post and all its engagement?')) return;
                    t.disabled = true;
                    try { await api('delete_post', { post_id: postId }); card.remove(); }
                    catch (err) { alert(err.message); }
                    t.disabled = false;
                }
            });

            <?php if ($isOwnProfile): ?>
            const backdrop  = document.getElementById('profModalBackdrop');
            const editBtn   = document.getElementById('profEditBtn');
            const closeBtn  = document.getElementById('profModalClose');
            const photoTrig = document.getElementById('profPhotoTrigger');
            const photoInp  = document.getElementById('profPhotoInput');

            const openModal  = () => backdrop.classList.add('open');
            const closeModal = () => backdrop.classList.remove('open');

            editBtn?.addEventListener('click',  openModal);
            photoTrig?.addEventListener('click', openModal);
            closeBtn?.addEventListener('click',  closeModal);
            backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

            photoInp?.addEventListener('change', function () {
                const file = this.files?.[0]; if (!file) return;
                const url = URL.createObjectURL(file);
                document.getElementById('profModalAvatarWrap').innerHTML =
                    '<img src="' + url + '" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--green-light);">';
            });

            document.getElementById('profEditForm')?.addEventListener('submit', async function (e) {
                e.preventDefault();
                const msg = document.getElementById('profSaveMsg');
                const btn = document.getElementById('profSaveBtn');
                msg.textContent = ''; msg.className = 'prof-msg'; btn.disabled = true;
                try {
                    const fd  = new FormData(this);
                    const res = await fetch('community_profile_api.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.ok) throw new Error(data.error || 'Could not save.');

                    if (data.photo_url) {
                        const newSrc = data.photo_url + '?t=' + Date.now();
                        document.getElementById('profPhotoWrap').querySelector('img,div').outerHTML =
                            '<img src="' + newSrc + '" class="prof-avatar-img" style="width:96px;height:96px;border-radius:50%;object-fit:cover;flex-shrink:0;border:3px solid var(--green-light);box-shadow:0 2px 10px var(--shadow);">';
                        document.getElementById('profModalAvatarWrap').innerHTML =
                            '<img src="' + newSrc + '" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--green-light);">';
                    }

                    const bioDisplay = document.getElementById('profBioDisplay');
                    if (bioDisplay) {
                        if (data.bio) {
                            bioDisplay.innerHTML = '<div class="prof-bio">' + data.bio.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</div>';
                        } else {
                            bioDisplay.innerHTML = '<div class="prof-bio-empty">No bio yet — click "Edit profile" to add one.</div>';
                        }
                    }

                    msg.textContent = '✓ Profile updated!';
                    msg.classList.add('ok');
                    setTimeout(closeModal, 1200);
                } catch (err) {
                    msg.textContent = err.message || 'Error saving.';
                    msg.classList.add('err');
                } finally {
                    btn.disabled = false;
                }
            });
            <?php endif; ?>
        })();
        </script>

        <?php endif; ?>
    </main>
</div>
</body>
</html>

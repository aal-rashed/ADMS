<?php
/**
 * Alumni Community Post Detail Page
 * Shows a single post with all its comments below it.
 * Hardcoded to alumni portal.
 */
require_once __DIR__ . '/adms_session.php';
$admsCommunityPortal = adms_session_start_community('alumni');
require __DIR__ . '/config.php';
require __DIR__ . '/community_lib.php';

$viewer = community_require_viewer();
$csrf   = community_ensure_csrf();

// Guard: only alumni
if (($viewer['type'] ?? '') !== 'alumni') {
    header('Location: alumni_login.php');
    exit;
}

$postId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($postId <= 0) {
    header('Location: alumni_community.php');
    exit;
}

$ready    = $pdo && community_tables_ready($pdo);
$post     = null;
$comments = [];
$meta     = ['like_counts' => [], 'comment_counts' => [], 'repost_counts' => [], 'liked' => [], 'reposted' => []];

if ($ready) {
    $st = $pdo->prepare('SELECT * FROM community_posts WHERE id = ?');
    $st->execute([$postId]);
    $post = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($ready && !$post) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;"><h2>Post not found</h2><a href="alumni_community.php">← Back to Community</a></body></html>';
    exit;
}

if ($ready && $post) {
    // Counts
    $st = $pdo->prepare('SELECT COUNT(*) AS c FROM community_likes WHERE post_id = ?');
    $st->execute([$postId]);
    $meta['like_counts'][$postId] = (int) $st->fetch()['c'];

    $st = $pdo->prepare('SELECT COUNT(*) AS c FROM community_comments WHERE post_id = ?');
    $st->execute([$postId]);
    $meta['comment_counts'][$postId] = (int) $st->fetch()['c'];

    $st = $pdo->prepare('SELECT COUNT(*) AS c FROM community_reposts WHERE original_post_id = ?');
    $st->execute([$postId]);
    $meta['repost_counts'][$postId] = (int) $st->fetch()['c'];

    // Liked / reposted by alumni viewer
    $st = $pdo->prepare("SELECT id FROM community_likes WHERE post_id = ? AND liker_type='alumni' AND liker_alumni_id = ?");
    $st->execute([$postId, (int) $viewer['alumni_id']]);
    if ($st->fetch()) $meta['liked'][$postId] = true;

    $st = $pdo->prepare("SELECT id FROM community_reposts WHERE original_post_id = ? AND reposter_type='alumni' AND reposter_alumni_id = ?");
    $st->execute([$postId, (int) $viewer['alumni_id']]);
    if ($st->fetch()) $meta['reposted'][$postId] = true;

    // Comments
    $st = $pdo->prepare('SELECT * FROM community_comments WHERE post_id = ? ORDER BY created_at ASC, id ASC');
    $st->execute([$postId]);
    $comments = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Resolve names + photos
$adminIds  = [];
$alumniIds = [];
if ($post) {
    if ($post['author_type'] === 'admin'  && $post['author_admin_id'])  $adminIds[]  = (int) $post['author_admin_id'];
    if ($post['author_type'] === 'alumni' && $post['author_alumni_id']) $alumniIds[] = (int) $post['author_alumni_id'];
}
foreach ($comments as $c) {
    if ($c['author_type'] === 'admin'  && $c['author_admin_id'])  $adminIds[]  = (int) $c['author_admin_id'];
    if ($c['author_type'] === 'alumni' && $c['author_alumni_id']) $alumniIds[] = (int) $c['author_alumni_id'];
}

$adminData   = ($ready && $pdo) ? community_map_admin_data($pdo, $adminIds) : [];
$alumniData  = ($ready && $pdo) ? community_map_alumni_data($pdo, $alumniIds) : [];
$adminNames  = array_map(fn($d) => $d['name'], $adminData);
$alumniNames = array_map(fn($d) => $d['name'], $alumniData);

/** Helper: profile link for alumni portal */
function alumni_post_profile_link(string $type, ?int $adminId, ?int $alumniId): string
{
    if ($type === 'alumni' && $alumniId) {
        return 'alumni_community_profile.php?type=alumni&id=' . $alumniId;
    }
    if ($type === 'admin' && $adminId) {
        return 'alumni_community_profile.php?type=admin&id=' . $adminId;
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post – Community – Alumni Portal</title>
    <meta name="adms-community-portal" content="alumni">
    <link rel="stylesheet" href="style.css">
    <style>
        .com-wrap        { max-width: 720px; }
        .com-muted       { color: var(--muted); font-size: 12px; margin-left: 6px; }
        .com-badge       { display:inline-block; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:2px 8px; border-radius:20px; vertical-align:middle; }
        .com-badge-admin { background:#e3f2fd; color:#1565c0; border:1px solid #90caf9; }
        .com-badge-alumni{ background:var(--green-light); color:var(--green-dark); border:1px solid #a5d6a7; }
        .com-mod-chip    { font-size:10px; font-weight:600; color:var(--green-dark); background:var(--green-light); border-radius:20px; padding:1px 8px; margin-left:6px; }
        .com-card        { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px 20px; box-shadow:0 2px 10px var(--shadow); margin-bottom:16px; }
        .com-card-head   { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:10px; }
        .com-card-author strong { font-size:14px; }
        .com-card-body .com-text { font-size:14px; line-height:1.65; margin-bottom:10px; word-break:break-word; }
        .com-img-wrap    { margin:10px 0; border-radius:var(--radius-md); overflow:hidden; border:1px solid var(--border); max-height:480px; }
        .com-img-wrap img{ width:100%; height:auto; display:block; object-fit:cover; }
        .com-link-chip   { display:inline-flex; align-items:center; gap:6px; margin-top:6px; padding:7px 12px; border-radius:var(--radius-sm); background:var(--green-light); color:var(--green-dark); font-size:12px; font-weight:600; border:1px solid #a5d6a7; word-break:break-all; }
        .com-card-foot   { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:12px; padding-top:12px; border-top:1px solid var(--border); }
        .post-comments-section { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:22px; box-shadow:0 2px 10px var(--shadow); }
        .post-comments-title { font-family:var(--font-display); font-size:15px; color:var(--green-dark); margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid var(--border); }
        .com-comment     { background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-md); padding:12px 14px; margin-bottom:10px; display:flex; gap:12px; }
        .com-comment-main { flex:1; min-width:0; }
        .com-comment-head{ display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:12px; margin-bottom:6px; }
        .com-comment-author { font-weight:600; color:var(--text); }
        .com-comment-author a { color:inherit; text-decoration:none; }
        .com-comment-author a:hover { color:var(--green-dark); }
        .com-comment-actions { margin-left:auto; }
        .com-comment-body { font-size:13px; line-height:1.55; word-break:break-word; }
        .add-comment-box { margin-top:18px; padding-top:18px; border-top:1px dashed var(--border); }
        .add-comment-box textarea { width:100%; }
        .back-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--green-dark); font-weight:500; margin-bottom:18px; }
        .back-link:hover { text-decoration:underline; }
        .com-author-link { color:var(--text); text-decoration:none; }
        .com-author-link:hover strong { color:var(--green-dark); }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/alumni_sidebar.php'; ?>
    <main class="main-content animate-fadeIn">

        <a class="back-link" href="alumni_community.php">← Back to Community</a>

        <?php if (!$pdo): ?>
            <div class="alert alert-error">Database unavailable.</div>
        <?php elseif (!$ready): ?>
            <div class="alert alert-warning">Community tables not installed.</div>
        <?php elseif (!$post): ?>
            <div class="alert alert-warning">Post not found.</div>
        <?php else:

            $pid = (int) $post['id'];
            $authorAlumniId = $post['author_alumni_id'] ? (int) $post['author_alumni_id'] : null;
            $authorAdminId  = $post['author_admin_id']  ? (int) $post['author_admin_id']  : null;
            $author = community_author_label($post['author_type'], $authorAdminId, $authorAlumniId, $adminNames, $alumniNames);
            $badge  = $post['author_type'] === 'admin' ? '<span class="com-badge com-badge-admin">Staff</span>' : '<span class="com-badge com-badge-alumni">Alumni</span>';

            $photoPath = null;
            if ($post['author_type'] === 'alumni' && $authorAlumniId && isset($alumniData[$authorAlumniId])) {
                $photoPath = $alumniData[$authorAlumniId]['photo'] ?? null;
            } elseif ($post['author_type'] === 'admin' && $authorAdminId && isset($adminData[$authorAdminId])) {
                $photoPath = $adminData[$authorAdminId]['photo'] ?? null;
            }
            $avatarHtml  = community_avatar_html($photoPath, $author, '44px');
            $profileLink = alumni_post_profile_link($post['author_type'], $authorAdminId, $authorAlumniId);

            $likes      = $meta['like_counts'][$pid] ?? 0;
            $reposts    = $meta['repost_counts'][$pid] ?? 0;
            $liked      = !empty($meta['liked'][$pid]);
            $reposted   = !empty($meta['reposted'][$pid]);
            $canDelPost = community_can_delete_post($viewer, $post);
            $likeClass  = $liked    ? 'btn btn-primary com-like-btn'   : 'btn btn-outline com-like-btn';
            $repClass   = $reposted ? 'btn btn-primary com-repost-btn' : 'btn btn-outline com-repost-btn';
        ?>

        <div class="com-wrap" id="comFeed">

            <article class="com-card" data-post-id="<?php echo $pid; ?>" data-feed-kind="post">
                <header class="com-card-head">
                    <div class="com-card-author" style="display:flex;align-items:center;gap:12px;">
                        <?php if ($profileLink): ?>
                            <a href="<?php echo htmlspecialchars($profileLink); ?>" style="line-height:0;flex-shrink:0;"><?php echo $avatarHtml; ?></a>
                        <?php else: echo $avatarHtml; endif; ?>
                        <div>
                            <?php if ($profileLink): ?>
                                <a href="<?php echo htmlspecialchars($profileLink); ?>" class="com-author-link"><strong><?php echo community_h($author); ?></strong></a>
                            <?php else: ?>
                                <strong><?php echo community_h($author); ?></strong>
                            <?php endif; ?>
                            <?php echo $badge; ?>
                            <span class="com-muted">· <?php echo community_h(community_format_time($post['created_at'])); ?></span>
                        </div>
                    </div>
                    <?php if ($canDelPost): ?>
                        <button type="button" class="btn btn-danger com-del-post" style="padding:5px 12px;font-size:11px;">Delete post</button>
                    <?php endif; ?>
                </header>

                <div class="com-card-body">
                    <?php if ($post['body']): ?>
                        <p class="com-text"><?php echo nl2br(community_h($post['body'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($post['image_path'])): ?>
                        <div class="com-img-wrap"><a href="<?php echo community_h($post['image_path']); ?>" target="_blank" rel="noopener noreferrer" title="Click to open full image"><img src="<?php echo community_h($post['image_path']); ?>" alt="" style="cursor:zoom-in;"></a></div>
                    <?php endif; ?>
                    <?php if (!empty($post['link_url'])): ?>
                        <a class="com-link-chip" href="<?php echo community_h($post['link_url']); ?>" target="_blank" rel="noopener noreferrer">🔗 <?php echo community_h($post['link_url']); ?></a>
                    <?php endif; ?>
                </div>

                <footer class="com-card-foot">
                    <button type="button" class="<?php echo $likeClass; ?>" data-liked="<?php echo $liked ? '1' : '0'; ?>">
                        ❤️ Like <span class="com-like-count"><?php echo $likes; ?></span>
                    </button>
                    <button type="button" class="<?php echo $repClass; ?>" data-reposted="<?php echo $reposted ? '1' : '0'; ?>">
                        🔁 Repost <span class="com-repost-count"><?php echo $reposts; ?></span>
                    </button>
                    <span class="com-muted">💬 <span id="commentTotal"><?php echo count($comments); ?></span> comment<?php echo count($comments) === 1 ? '' : 's'; ?></span>
                </footer>
            </article>

            <section class="post-comments-section">
                <div class="post-comments-title">💬 Comments (<span id="commentTotal2"><?php echo count($comments); ?></span>)</div>

                <div class="com-comment-list" id="commentList">
                <?php if (!$comments): ?>
                    <div style="text-align:center;color:var(--muted);padding:24px 12px;font-style:italic;font-size:13px;">No comments yet. Be the first.</div>
                <?php else:
                    foreach ($comments as $c):
                        $cid   = (int) $c['id'];
                        $cAdId = $c['author_admin_id']  ? (int) $c['author_admin_id']  : null;
                        $cAlId = $c['author_alumni_id'] ? (int) $c['author_alumni_id'] : null;
                        $cWho  = community_author_label($c['author_type'], $cAdId, $cAlId, $adminNames, $alumniNames);
                        $cPhoto = null;
                        if ($c['author_type'] === 'alumni' && $cAlId && isset($alumniData[$cAlId])) $cPhoto = $alumniData[$cAlId]['photo'] ?? null;
                        if ($c['author_type'] === 'admin'  && $cAdId && isset($adminData[$cAdId]))  $cPhoto = $adminData[$cAdId]['photo']  ?? null;
                        $cAvatar = community_avatar_html($cPhoto, $cWho, '34px');
                        $cLink   = alumni_post_profile_link($c['author_type'], $cAdId, $cAlId);
                        $cMod    = ($c['author_type'] === 'admin') ? '<span class="com-mod-chip">Staff</span>' : '';
                        $cCanDel = community_can_delete_comment($viewer, $c);
                ?>
                    <div class="com-comment" data-comment-id="<?php echo $cid; ?>">
                        <?php if ($cLink): ?>
                            <a href="<?php echo htmlspecialchars($cLink); ?>" style="line-height:0;flex-shrink:0;"><?php echo $cAvatar; ?></a>
                        <?php else: echo $cAvatar; endif; ?>
                        <div class="com-comment-main">
                            <div class="com-comment-head">
                                <span class="com-comment-author">
                                    <?php if ($cLink): ?>
                                        <a href="<?php echo htmlspecialchars($cLink); ?>"><?php echo community_h($cWho); ?></a>
                                    <?php else: ?>
                                        <?php echo community_h($cWho); ?>
                                    <?php endif; ?>
                                </span>
                                <?php echo $cMod; ?>
                                <span class="com-muted"><?php echo community_h(community_format_time($c['created_at'])); ?></span>
                                <span class="com-comment-actions">
                                    <?php if ($cCanDel): ?>
                                        <button type="button" class="com-del-comment btn btn-outline" data-comment-id="<?php echo $cid; ?>" style="padding:4px 10px;font-size:11px;">Delete</button>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="com-comment-body"><?php echo nl2br(community_h($c['body'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
                </div>

                <div class="add-comment-box">
                    <label for="postCommentInput" style="display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px;">Add a comment</label>
                    <textarea id="postCommentInput" class="field" rows="3" maxlength="2000" placeholder="Write a comment…"></textarea>
                    <button type="button" class="btn btn-primary" id="postCommentBtn" style="margin-top:8px;">Post comment</button>
                    <span id="postCommentMsg" style="margin-left:10px;font-size:12px;color:var(--muted);"></span>
                </div>
            </section>

        </div>

        <script>
        (function () {
            const CSRF    = <?php echo json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const PORTAL  = 'alumni';
            const POST_ID = <?php echo (int)$pid; ?>;

            async function api(action, payload) {
                const body = Object.assign({ action, csrf: CSRF, portal: PORTAL }, payload || {});
                const res = await fetch('community_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-ADMS-PORTAL': PORTAL },
                    body: JSON.stringify(body),
                });
                let data;
                try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Bad response' }; }
                if (!data.ok) throw new Error(data.error || 'Request failed');
                return data;
            }

            function updateCommentTotal(n) {
                document.getElementById('commentTotal').textContent  = n;
                document.getElementById('commentTotal2').textContent = n;
            }

            const card = document.querySelector('.com-card[data-post-id]');

            card.querySelector('.com-like-btn')?.addEventListener('click', async function (e) {
                const btn = e.currentTarget;
                btn.disabled = true;
                try {
                    const data = await api('toggle_like', { post_id: POST_ID });
                    btn.querySelector('.com-like-count').textContent = data.like_count;
                    btn.dataset.liked = data.liked ? '1' : '0';
                    btn.classList.toggle('btn-primary', data.liked);
                    btn.classList.toggle('btn-outline', !data.liked);
                } catch (err) { alert(err.message); }
                btn.disabled = false;
            });

            card.querySelector('.com-repost-btn')?.addEventListener('click', async function (e) {
                const btn = e.currentTarget;
                btn.disabled = true;
                try {
                    const data = await api('toggle_repost', { post_id: POST_ID });
                    btn.querySelector('.com-repost-count').textContent = data.repost_count;
                    btn.dataset.reposted = data.reposted ? '1' : '0';
                    btn.classList.toggle('btn-primary', data.reposted);
                    btn.classList.toggle('btn-outline', !data.reposted);
                } catch (err) { alert(err.message); }
                btn.disabled = false;
            });

            card.querySelector('.com-del-post')?.addEventListener('click', async function () {
                if (!confirm('Delete this post and all its engagement?')) return;
                try {
                    await api('delete_post', { post_id: POST_ID });
                    window.location.href = 'alumni_community.php';
                } catch (err) { alert(err.message); }
            });

            document.getElementById('postCommentBtn')?.addEventListener('click', async function () {
                const ta  = document.getElementById('postCommentInput');
                const msg = document.getElementById('postCommentMsg');
                const body = (ta.value || '').trim();
                if (!body) { ta.focus(); return; }
                this.disabled = true;
                msg.textContent = '';
                try {
                    const data = await api('add_comment', { post_id: POST_ID, body });
                    const list = document.getElementById('commentList');
                    const empty = list.querySelector('div[style*="italic"]');
                    if (empty) empty.remove();
                    if (data.html) {
                        const wrap = document.createElement('div');
                        wrap.innerHTML = data.html.trim();
                        list.appendChild(wrap.firstChild);
                    }
                    ta.value = '';
                    updateCommentTotal(data.comment_count);
                } catch (err) { msg.textContent = err.message; msg.style.color = 'var(--red)'; }
                this.disabled = false;
            });

            document.getElementById('commentList')?.addEventListener('click', async function (e) {
                const t = e.target;
                if (!t.classList.contains('com-del-comment')) return;
                const cid = parseInt(t.dataset.commentId || '0', 10);
                if (!cid || !confirm('Delete this comment?')) return;
                t.disabled = true;
                try {
                    const data = await api('delete_comment', { comment_id: cid });
                    t.closest('.com-comment')?.remove();
                    updateCommentTotal(data.comment_count);
                    const list = document.getElementById('commentList');
                    if (!list.querySelector('.com-comment')) {
                        list.innerHTML = '<div style="text-align:center;color:var(--muted);padding:24px 12px;font-style:italic;font-size:13px;">No comments yet. Be the first.</div>';
                    }
                } catch (err) { alert(err.message); }
                t.disabled = false;
            });

        })();
        </script>

        <?php endif; ?>
    </main>
</div>
</body>
</html>

<?php
/**
 * Admin Community Page — admin-only portal.
 * Hardcoded to portal=admin. Reached from sidebar.php.
 */
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/community_lib.php';

// Guard: only admins may use this page (admin portal uses default PHP session)
if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
$_SESSION['user_role'] = 'admin'; // ensure community_resolve_auth_conflict picks admin
$admsCommunityPortal   = 'admin';

$viewer = community_require_viewer();
$csrf   = community_ensure_csrf();

// Defense in depth: confirm community_current_viewer agrees we're an admin
if (($viewer['type'] ?? '') !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

$ready = $pdo && community_tables_ready($pdo);
$feed  = ['items' => [], 'posts' => [], 'comments_by_post' => [], 'meta' => ['like_counts' => [], 'comment_counts' => [], 'repost_counts' => [], 'liked' => [], 'reposted' => []]];

if ($ready) {
    $feed = community_build_feed($pdo, $viewer, 40);
}

/** Collect ids for name resolution */
$adminIds  = [];
$alumniIds = [];
foreach ($feed['posts'] as $p) {
    if ($p['author_type'] === 'admin' && $p['author_admin_id']) {
        $adminIds[] = (int) $p['author_admin_id'];
    }
    if ($p['author_type'] === 'alumni' && $p['author_alumni_id']) {
        $alumniIds[] = (int) $p['author_alumni_id'];
    }
}
foreach ($feed['items'] as $it) {
    if ($it['kind'] === 'repost' && $it['repost']) {
        $r = $it['repost'];
        if ($r['reposter_type'] === 'admin' && $r['reposter_admin_id']) {
            $adminIds[] = (int) $r['reposter_admin_id'];
        }
        if ($r['reposter_type'] === 'alumni' && $r['reposter_alumni_id']) {
            $alumniIds[] = (int) $r['reposter_alumni_id'];
        }
    }
}
foreach ($feed['comments_by_post'] as $list) {
    foreach ($list as $c) {
        if ($c['author_type'] === 'admin' && $c['author_admin_id']) {
            $adminIds[] = (int) $c['author_admin_id'];
        }
        if ($c['author_type'] === 'alumni' && $c['author_alumni_id']) {
            $alumniIds[] = (int) $c['author_alumni_id'];
        }
    }
}

$adminData   = ($ready && $pdo) ? community_map_admin_data($pdo,  $adminIds)  : [];
$alumniData  = ($ready && $pdo) ? community_map_alumni_data($pdo, $alumniIds) : [];
$adminNames  = array_map(fn($d) => $d['name'], $adminData);
$alumniNames = array_map(fn($d) => $d['name'], $alumniData);

$meta = $feed['meta'];

/**
 * Render a single post block. Uses admin-specific page filenames for links.
 */
function admin_community_render_post_block(array $post, array $viewer, array $adminNames, array $alumniNames, array $meta, array $comments, ?array $repostRow = null, array $alumniData = [], array $adminData = []): void
{
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
    $avatarHtml = community_avatar_html($photoPath, $author, '38px');

    // Profile link → admin profile page
    $authorLink = '';
    if ($post['author_type'] === 'alumni' && $authorAlumniId) {
        $authorLink = 'admin_community_profile.php?type=alumni&id=' . $authorAlumniId;
    } elseif ($post['author_type'] === 'admin' && $authorAdminId) {
        $authorLink = 'admin_community_profile.php?type=admin&id=' . $authorAdminId;
    }

    // Post detail page link → admin post page
    $postLink = 'admin_community_post.php?id=' . $pid;

    $likes       = $meta['like_counts'][$pid]    ?? 0;
    $commentsC   = $meta['comment_counts'][$pid] ?? 0;
    $reposts     = $meta['repost_counts'][$pid]  ?? 0;
    $liked       = !empty($meta['liked'][$pid]);
    $reposted    = !empty($meta['reposted'][$pid]);

    $canDelPost   = community_can_delete_post($viewer, $post);
    $canDelRepost = $repostRow && community_can_delete_repost($viewer, $repostRow);

    $likeClass = $liked ? 'btn btn-primary com-like-btn' : 'btn btn-outline com-like-btn';
    $repClass  = $reposted ? 'btn btn-primary com-repost-btn' : 'btn btn-outline com-repost-btn';

    $extra = $repostRow ? ' com-card-repost' : '';
    $rid   = $repostRow ? (int) $repostRow['id'] : 0;
    echo '<article class="com-card' . $extra . '" data-post-id="' . $pid . '" data-feed-kind="' . ($repostRow ? 'repost' : 'post') . '"' . ($rid ? ' data-repost-id="' . $rid . '"' : '') . '>';
    if ($repostRow) {
        $rAlumniId = $repostRow['reposter_alumni_id'] ? (int)$repostRow['reposter_alumni_id'] : null;
        $rAdminId  = $repostRow['reposter_admin_id']  ? (int)$repostRow['reposter_admin_id']  : null;
        $rp = community_author_label($repostRow['reposter_type'], $rAdminId, $rAlumniId, $adminNames, $alumniNames);
        $rpLink = '';
        if ($repostRow['reposter_type'] === 'alumni' && $rAlumniId) {
            $rpLink = 'admin_community_profile.php?type=alumni&id=' . $rAlumniId;
        } elseif ($repostRow['reposter_type'] === 'admin' && $rAdminId) {
            $rpLink = 'admin_community_profile.php?type=admin&id=' . $rAdminId;
        }
        $rpName = $rpLink
            ? '<a href="' . community_h($rpLink) . '" style="color:var(--green-dark);text-decoration:none;font-weight:600;">' . community_h($rp) . '</a>'
            : community_h($rp);
        echo '<div class="com-repost-banner"><span>🔁</span><span>' . $rpName . ' reposted</span></div>';
    }

    $authorDisplay = $authorLink
        ? '<a href="' . community_h($authorLink) . '" style="color:var(--text);text-decoration:none;" class="com-author-link"><strong>' . community_h($author) . '</strong></a>'
        : '<strong>' . community_h($author) . '</strong>';

    echo '<header class="com-card-head">';
    echo '<div class="com-card-author" style="display:flex;align-items:center;gap:10px;">';
    if ($authorLink) {
        echo '<a href="' . community_h($authorLink) . '" style="line-height:0;flex-shrink:0;">' . $avatarHtml . '</a>';
    } else {
        echo $avatarHtml;
    }
    echo '<div>' . $authorDisplay . ' ' . $badge;
    echo '<span class="com-muted">· ' . community_h(community_format_time($post['created_at'])) . '</span></div>';
    echo '</div>';
    echo '<div class="com-card-actions" style="display:flex;gap:6px;flex-wrap:wrap;">';
    if ($canDelRepost) {
        echo '<button type="button" class="btn btn-outline com-del-repost" style="padding:5px 12px;font-size:11px;">Remove repost</button>';
    }
    if ($canDelPost) {
        echo '<button type="button" class="btn btn-danger com-del-post" style="padding:5px 12px;font-size:11px;">Delete post</button>';
    }
    echo '</div></header><div class="com-card-body">';

    if ($post['body']) {
        echo '<a href="' . community_h($postLink) . '" style="color:inherit;text-decoration:none;display:block;"><p class="com-text">' . nl2br(community_h($post['body'])) . '</p></a>';
    }
    if (!empty($post['image_path'])) {
        echo '<a href="' . community_h($postLink) . '" style="display:block;"><div class="com-img-wrap"><img src="' . community_h($post['image_path']) . '" alt=""></div></a>';
    }
    if (!empty($post['link_url'])) {
        $u = community_h($post['link_url']);
        echo '<a class="com-link-chip" href="' . $u . '" target="_blank" rel="noopener noreferrer">🔗 ' . $u . '</a>';
    }

    echo '</div><footer class="com-card-foot">';
    echo '<button type="button" class="' . $likeClass . '" data-liked="' . ($liked ? '1' : '0') . '">❤️ Like <span class="com-like-count">' . (int) $likes . '</span></button>';
    echo '<button type="button" class="' . $repClass . '" data-reposted="' . ($reposted ? '1' : '0') . '">🔁 Repost <span class="com-repost-count">' . (int) $reposts . '</span></button>';
    echo '<a class="com-comments-link" href="' . community_h($postLink) . '" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--radius-sm);background:var(--off-white);border:1px solid var(--border);color:var(--text);font-size:13px;font-weight:500;text-decoration:none;transition:background .2s;">💬 <span class="com-comment-total-count">' . (int) $commentsC . '</span> comment' . ($commentsC === 1 ? '' : 's') . '</a>';
    echo '</footer>';

    echo '</article>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community – ADMS Admin</title>
    <meta name="adms-community-portal" content="admin">
    <link rel="stylesheet" href="style.css">
    <style>
        .com-wrap { max-width: 720px; }
        .com-muted { color: var(--muted); font-size: 12px; margin-left: 6px; }
        .com-badge { display:inline-block; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:2px 8px; border-radius:20px; vertical-align:middle; }
        .com-badge-admin { background:#e3f2fd; color:#1565c0; border:1px solid #90caf9; }
        .com-badge-alumni { background:var(--green-light); color:var(--green-dark); border:1px solid #a5d6a7; }
        .com-mod-chip { font-size:10px; font-weight:600; color:var(--green-dark); background:var(--green-light); border-radius:20px; padding:1px 8px; margin-left:6px; }
        .com-composer { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:22px; box-shadow:0 2px 10px var(--shadow); margin-bottom:22px; }
        .com-composer h3 { font-family:var(--font-display); font-size:16px; color:var(--green-dark); margin-bottom:12px; }
        .com-feed { display:flex; flex-direction:column; gap:16px; }
        .com-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px 20px; box-shadow:0 2px 10px var(--shadow); }
        .com-card-repost { border-left:4px solid var(--green); background:linear-gradient(135deg,var(--green-light) 0%,var(--white) 45%); }
        .com-repost-banner { font-size:12px; font-weight:600; color:var(--green-dark); margin-bottom:10px; display:flex; align-items:center; gap:8px; }
        .com-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:10px; }
        .com-card-author strong { font-size:14px; }
        .com-card-body .com-text { font-size:14px; line-height:1.65; margin-bottom:10px; word-break:break-word; }
        .com-img-wrap { margin:10px 0; border-radius:var(--radius-md); overflow:hidden; border:1px solid var(--border); max-height:360px; }
        .com-img-wrap img { width:100%; height:auto; display:block; object-fit:cover; }
        .com-link-chip { display:inline-flex; align-items:center; gap:6px; margin-top:6px; padding:7px 12px; border-radius:var(--radius-sm); background:var(--green-light); color:var(--green-dark); font-size:12px; font-weight:600; border:1px solid #a5d6a7; word-break:break-all; }
        .com-card-foot { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:12px; padding-top:12px; border-top:1px solid var(--border); }
        .com-comments { margin-top:14px; padding-top:12px; border-top:1px dashed var(--border); }
        .com-comments-title { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:8px; }
        .com-comment { background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:10px 12px; margin-bottom:8px; }
        .com-comment-head { display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:12px; margin-bottom:4px; }
        .com-comment-author { font-weight:600; color:var(--text); }
        .com-comment-actions { margin-left:auto; }
        .com-comment-body { font-size:13px; line-height:1.55; word-break:break-word; }
        .com-add-comment textarea { width:100%; }
        .com-banner-mod { background:#fff8e1; border:1px solid #ffe082; color:#e65100; padding:10px 14px; border-radius:var(--radius-sm); font-size:13px; margin-bottom:16px; }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content animate-fadeIn">
        <div class="page-header">
            <div>
                <h1>Community</h1>
                <p class="page-sub">Share updates, celebrate milestones, and stay connected with graduates and staff.</p>
            </div>
        </div>

        <?php if (!$pdo): ?>
            <div class="alert alert-error">PDO could not be initialized. Check MySQL credentials in <code>config.php</code>.</div>
        <?php elseif (!$ready): ?>
            <div class="alert alert-warning">
                The Community tables are not installed yet. Import <strong>community_schema.sql</strong> into the <code>adms</code> database, then refresh this page.
            </div>
        <?php else: ?>

            <?php if (!empty($viewer['is_moderator'])): ?>
                <div class="com-banner-mod">You are signed in as an <strong>administrator</strong>. You may delete any post or comment for moderation.</div>
            <?php endif; ?>

            <div class="com-wrap">
                <div class="com-composer card" style="padding:22px;">
                    <h3>Create a post</h3>
                    <form id="comCreateForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?php echo community_h($csrf); ?>">
                        <input type="hidden" name="portal" value="admin">
                        <div class="field">
                            <label for="com_body">Text</label>
                            <textarea id="com_body" name="body" rows="3" maxlength="8000" placeholder="What would you like to share?"></textarea>
                        </div>
                        <div class="grid-2">
                            <div class="field">
                                <label for="com_link">Link (optional)</label>
                                <input id="com_link" type="url" name="link_url" placeholder="https://">
                            </div>
                            <div class="field">
                                <label for="com_img">Photo (optional)</label>
                                <input id="com_img" type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="comPublishBtn">Publish</button>
                        <span class="com-muted" id="comCreateMsg" style="margin-left:10px;"></span>
                    </form>
                </div>

                <div class="com-feed" id="comFeed">
                    <?php
                    foreach ($feed['items'] as $it) {
                        $pid = $it['post_id'];
                        $post = $feed['posts'][$pid] ?? null;
                        if (!$post) {
                            continue;
                        }
                        $comments = $feed['comments_by_post'][$pid] ?? [];
                        $re       = ($it['kind'] === 'repost' && $it['repost']) ? $it['repost'] : null;
                        admin_community_render_post_block($post, $viewer, $adminNames, $alumniNames, $meta, $comments, $re, $alumniData, $adminData);
                    }
                    if (!$feed['items']) {
                        echo '<div class="card" style="padding:40px;text-align:center;color:var(--muted);">No posts yet. Be the first to share something with the community.</div>';
                    }
                    ?>
                </div>
            </div>

            <script>
            (function () {
                const CSRF   = <?php echo json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
                const PORTAL = 'admin';

                async function api(action, payload) {
                    const body = Object.assign({ action, csrf: CSRF, portal: PORTAL }, payload || {});
                    const res = await fetch('community_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-ADMS-PORTAL': PORTAL,
                        },
                        body: JSON.stringify(body)
                    });
                    let data;
                    try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Bad response' }; }
                    if (!data.ok) throw new Error(data.error || 'Request failed');
                    return data;
                }

                function postCard(el) { return el.closest('.com-card[data-post-id]'); }

                document.getElementById('comCreateForm')?.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const msg = document.getElementById('comCreateMsg');
                    const btn = document.getElementById('comPublishBtn');
                    msg.textContent = '';
                    btn.disabled = true;
                    try {
                        const fd = new FormData(this);
                        const res = await fetch('community_create_post.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (!data.ok) throw new Error(data.error || 'Could not publish');
                        msg.textContent = 'Published! Reloading…';
                        window.location.reload();
                    } catch (err) {
                        msg.textContent = err.message || 'Error';
                    } finally {
                        btn.disabled = false;
                    }
                });

                document.getElementById('comFeed')?.addEventListener('click', async function (e) {
                    const t = e.target;
                    if (t.classList.contains('com-like-btn')) {
                        const card = postCard(t);
                        const postId = parseInt(card?.dataset.postId || '0', 10);
                        if (!postId) return;
                        t.disabled = true;
                        try {
                            const data = await api('toggle_like', { post_id: postId });
                            const c = card.querySelector('.com-like-count');
                            if (c) c.textContent = data.like_count;
                            t.dataset.liked = data.liked ? '1' : '0';
                            t.classList.toggle('btn-primary', data.liked);
                            t.classList.toggle('btn-outline', !data.liked);
                        } catch (err) { alert(err.message); }
                        t.disabled = false;
                        return;
                    }
                    if (t.classList.contains('com-repost-btn')) {
                        const card = postCard(t);
                        const postId = parseInt(card?.dataset.postId || '0', 10);
                        if (!postId) return;
                        t.disabled = true;
                        try {
                            const data = await api('toggle_repost', { post_id: postId });
                            const c = card.querySelector('.com-repost-count');
                            if (c) c.textContent = data.repost_count;
                            t.dataset.reposted = data.reposted ? '1' : '0';
                            t.classList.toggle('btn-primary', data.reposted);
                            t.classList.toggle('btn-outline', !data.reposted);
                        } catch (err) { alert(err.message); }
                        t.disabled = false;
                        return;
                    }
                    if (t.classList.contains('com-del-repost')) {
                        const card = postCard(t);
                        const rid = parseInt(card?.dataset.repostId || '0', 10);
                        if (!rid || !confirm('Remove this repost from the feed?')) return;
                        t.disabled = true;
                        try {
                            await api('delete_repost', { repost_id: rid });
                            card.remove();
                        } catch (err) { alert(err.message); }
                        t.disabled = false;
                        return;
                    }
                    if (t.classList.contains('com-del-post')) {
                        const card = postCard(t);
                        const postId = parseInt(card?.dataset.postId || '0', 10);
                        if (!postId || !confirm('Delete this post and all of its engagement?')) return;
                        t.disabled = true;
                        try {
                            await api('delete_post', { post_id: postId });
                            card.remove();
                        } catch (err) { alert(err.message); }
                        t.disabled = false;
                    }
                });
            })();
            </script>
        <?php endif; ?>
    </main>
</div>
</body>
</html>

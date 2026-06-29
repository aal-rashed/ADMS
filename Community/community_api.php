<?php
/**
 * Community JSON API — likes, reposts, comments, deletes (PDO + fetch from browser).
 */
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$portalPick = $data['portal'] ?? ($_SERVER['HTTP_X_ADMS_PORTAL'] ?? '');
$portalPick = in_array($portalPick, ['admin', 'alumni'], true) ? $portalPick : null;

require_once __DIR__ . '/adms_session.php';

// Admin portal uses the default PHP session (PHPSESSID), not the split ADMS_ADMIN cookie.
// Alumni portal uses the split ADMS_ALUMNI cookie via adms_session_start_community.
if ($portalPick === 'admin') {
    session_start();
} else {
    adms_session_start_community($portalPick);
    // Fallback to admin's default PHP session if no alumni split-session login is present
    if (empty($_SESSION['alumni_id']) && empty($_SESSION['admin_id']) && !empty($_COOKIE['PHPSESSID'])) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name('PHPSESSID');
        session_start();
    }
}

require __DIR__ . '/config.php';
require __DIR__ . '/community_lib.php';

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

$viewer = community_current_viewer();
if (!$viewer) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!community_tables_ready($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Community tables not installed. Run community_schema.sql']);
    exit;
}

$action = $data['action'] ?? '';
$csrf   = $data['csrf'] ?? '';

if (!community_verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token. Refresh the page.']);
    exit;
}

try {
    switch ($action) {
        case 'toggle_like':
            community_api_toggle_like($pdo, $viewer, (int) ($data['post_id'] ?? 0));
            break;
        case 'toggle_repost':
            community_api_toggle_repost($pdo, $viewer, (int) ($data['post_id'] ?? 0));
            break;
        case 'add_comment':
            community_api_add_comment($pdo, $viewer, (int) ($data['post_id'] ?? 0), trim((string) ($data['body'] ?? '')));
            break;
        case 'delete_comment':
            community_api_delete_comment($pdo, $viewer, (int) ($data['comment_id'] ?? 0));
            break;
        case 'delete_post':
            community_api_delete_post($pdo, $viewer, (int) ($data['post_id'] ?? 0));
            break;
        case 'delete_repost':
            community_api_delete_repost($pdo, $viewer, (int) ($data['repost_id'] ?? 0));
            break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}

function community_api_toggle_like(PDO $pdo, array $viewer, int $postId): void
{
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid post']);
        return;
    }
    $chk = $pdo->prepare('SELECT id FROM community_posts WHERE id = ?');
    $chk->execute([$postId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Post not found']);
        return;
    }

    if ($viewer['type'] === 'admin') {
        $sel = $pdo->prepare('SELECT id FROM community_likes WHERE post_id = ? AND liker_type = ? AND liker_admin_id = ? AND liker_alumni_id IS NULL');
        $sel->execute([$postId, 'admin', $viewer['admin_id']]);
        $exists = $sel->fetch();
        if ($exists) {
            $del = $pdo->prepare('DELETE FROM community_likes WHERE id = ?');
            $del->execute([(int) $exists['id']]);
            $liked = false;
        } else {
            $ins = $pdo->prepare('INSERT INTO community_likes (post_id, liker_type, liker_admin_id, liker_alumni_id) VALUES (?,?,?,NULL)');
            $ins->execute([$postId, 'admin', $viewer['admin_id']]);
            $liked = true;
        }
    } else {
        $sel = $pdo->prepare('SELECT id FROM community_likes WHERE post_id = ? AND liker_type = ? AND liker_alumni_id = ? AND liker_admin_id IS NULL');
        $sel->execute([$postId, 'alumni', $viewer['alumni_id']]);
        $exists = $sel->fetch();
        if ($exists) {
            $del = $pdo->prepare('DELETE FROM community_likes WHERE id = ?');
            $del->execute([(int) $exists['id']]);
            $liked = false;
        } else {
            $ins = $pdo->prepare('INSERT INTO community_likes (post_id, liker_type, liker_admin_id, liker_alumni_id) VALUES (?,?,NULL,?)');
            $ins->execute([$postId, 'alumni', $viewer['alumni_id']]);
            $liked = true;
        }
    }

    $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM community_likes WHERE post_id = ?');
    $cnt->execute([$postId]);
    $count = (int) $cnt->fetch()['c'];

    echo json_encode(['ok' => true, 'liked' => $liked, 'like_count' => $count]);
}

function community_api_toggle_repost(PDO $pdo, array $viewer, int $postId): void
{
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid post']);
        return;
    }
    $chk = $pdo->prepare('SELECT id FROM community_posts WHERE id = ?');
    $chk->execute([$postId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Post not found']);
        return;
    }

    if ($viewer['type'] === 'admin') {
        $sel = $pdo->prepare('SELECT id FROM community_reposts WHERE original_post_id = ? AND reposter_type = ? AND reposter_admin_id = ? AND reposter_alumni_id IS NULL');
        $sel->execute([$postId, 'admin', $viewer['admin_id']]);
        $exists = $sel->fetch();
        if ($exists) {
            $del = $pdo->prepare('DELETE FROM community_reposts WHERE id = ?');
            $del->execute([(int) $exists['id']]);
            $reposted = false;
        } else {
            $ins = $pdo->prepare('INSERT INTO community_reposts (original_post_id, reposter_type, reposter_admin_id, reposter_alumni_id) VALUES (?,?,?,NULL)');
            $ins->execute([$postId, 'admin', $viewer['admin_id']]);
            $reposted = true;
        }
    } else {
        $sel = $pdo->prepare('SELECT id FROM community_reposts WHERE original_post_id = ? AND reposter_type = ? AND reposter_alumni_id = ? AND reposter_admin_id IS NULL');
        $sel->execute([$postId, 'alumni', $viewer['alumni_id']]);
        $exists = $sel->fetch();
        if ($exists) {
            $del = $pdo->prepare('DELETE FROM community_reposts WHERE id = ?');
            $del->execute([(int) $exists['id']]);
            $reposted = false;
        } else {
            $ins = $pdo->prepare('INSERT INTO community_reposts (original_post_id, reposter_type, reposter_admin_id, reposter_alumni_id) VALUES (?,?,NULL,?)');
            $ins->execute([$postId, 'alumni', $viewer['alumni_id']]);
            $reposted = true;
        }
    }

    $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM community_reposts WHERE original_post_id = ?');
    $cnt->execute([$postId]);
    $count = (int) $cnt->fetch()['c'];

    echo json_encode(['ok' => true, 'reposted' => $reposted, 'repost_count' => $count]);
}

function community_api_add_comment(PDO $pdo, array $viewer, int $postId, string $body): void
{
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid post']);
        return;
    }
    if ($body === '' || mb_strlen($body) > 2000) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Comment must be 1–2000 characters.']);
        return;
    }
    $chk = $pdo->prepare('SELECT id FROM community_posts WHERE id = ?');
    $chk->execute([$postId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Post not found']);
        return;
    }

    if ($viewer['type'] === 'admin') {
        $ins = $pdo->prepare('INSERT INTO community_comments (post_id, author_type, author_admin_id, author_alumni_id, body) VALUES (?,?,?,?,?)');
        $ins->execute([$postId, 'admin', $viewer['admin_id'], null, $body]);
    } else {
        $ins = $pdo->prepare('INSERT INTO community_comments (post_id, author_type, author_admin_id, author_alumni_id, body) VALUES (?,?,NULL,?,?)');
        $ins->execute([$postId, 'alumni', $viewer['alumni_id'], $body]);
    }
    $cid = (int) $pdo->lastInsertId();

    $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM community_comments WHERE post_id = ?');
    $cnt->execute([$postId]);
    $count = (int) $cnt->fetch()['c'];

    $who = community_h($viewer['display_name']);
    $when = community_format_time(date('Y-m-d H:i:s'));

    echo json_encode([
        'ok'            => true,
        'comment_id'    => $cid,
        'comment_count' => $count,
        'html'          => community_api_comment_html($cid, $who, $when, community_h($body), $viewer),
    ]);
}

function community_api_comment_html(int $id, string $whoLabel, string $when, string $bodyHtml, array $viewer): string
{
    $mod = ($viewer['type'] === 'admin') ? '<span class="com-mod-chip">Staff</span>' : '';
    $del = '<button type="button" class="com-del-comment btn btn-outline" data-comment-id="' . $id . '" style="padding:4px 10px;font-size:11px;">Delete</button>';
    return '<div class="com-comment" data-comment-id="' . $id . '"><div class="com-comment-head"><span class="com-comment-author">' . $whoLabel . '</span> ' . $mod . '<span class="com-muted">' . $when . '</span><span class="com-comment-actions">' . $del . '</span></div><div class="com-comment-body">' . $bodyHtml . '</div></div>';
}

function community_api_delete_comment(PDO $pdo, array $viewer, int $commentId): void
{
    if ($commentId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid comment']);
        return;
    }
    $st = $pdo->prepare('SELECT * FROM community_comments WHERE id = ?');
    $st->execute([$commentId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Comment not found']);
        return;
    }
    if (!community_authorizes_comment_delete($row)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        return;
    }
    $pid = (int) $row['post_id'];
    $del = $pdo->prepare('DELETE FROM community_comments WHERE id = ?');
    $del->execute([$commentId]);

    $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM community_comments WHERE post_id = ?');
    $cnt->execute([$pid]);
    $count = (int) $cnt->fetch()['c'];

    echo json_encode(['ok' => true, 'post_id' => $pid, 'comment_count' => $count]);
}

function community_api_delete_repost(PDO $pdo, array $viewer, int $repostId): void
{
    if ($repostId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid repost']);
        return;
    }
    $st = $pdo->prepare('SELECT * FROM community_reposts WHERE id = ?');
    $st->execute([$repostId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Repost not found']);
        return;
    }
    if (!community_authorizes_repost_delete($row)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        return;
    }
    $del = $pdo->prepare('DELETE FROM community_reposts WHERE id = ?');
    $del->execute([$repostId]);
    echo json_encode(['ok' => true]);
}

function community_api_delete_post(PDO $pdo, array $viewer, int $postId): void
{
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid post']);
        return;
    }
    $st = $pdo->prepare('SELECT * FROM community_posts WHERE id = ?');
    $st->execute([$postId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Post not found']);
        return;
    }
    if (!community_authorizes_post_delete($row)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        return;
    }

    if (!empty($row['image_path']) && is_file($row['image_path'])) {
        @unlink($row['image_path']);
    }

    $del = $pdo->prepare('DELETE FROM community_posts WHERE id = ?');
    $del->execute([$postId]);

    echo json_encode(['ok' => true]);
}

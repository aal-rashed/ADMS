<?php
/**
 * Community module helpers — session viewer + PDO utilities.
 * All DB access for Community uses PDO from config.php ($pdo).
 */

function community_tables_ready(?PDO $pdo): bool
{
    if (!$pdo) {
        return false;
    }
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('community_posts','community_likes','community_comments','community_reposts')")->fetch()['c'];
        return $n === 4;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * If both admin and alumni keys exist (mixed session), keep only the role from last login.
 * Prevents alumni from being treated as admins when `admin_id` was left over in the same PHP session.
 */
function community_resolve_auth_conflict(): void
{
    if (empty($_SESSION['admin_id']) || empty($_SESSION['alumni_id'])) {
        return;
    }
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') {
        unset($_SESSION['alumni_id'], $_SESSION['alumni_name'], $_SESSION['alumni_student_id']);
    } else {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    }
}

/** True when an admin account is logged in (moderation / god-mode checks must use this, not only $viewer). */
function community_session_is_admin(): bool
{
    return isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0;
}

/** True when an alumni portal user is logged in. */
function community_session_is_alumni(): bool
{
    return isset($_SESSION['alumni_id']) && (int) $_SESSION['alumni_id'] > 0;
}

/** @return array|null { type, user_role, is_moderator, admin_id?, alumni_id?, display_name } */
function community_current_viewer(): ?array
{
    community_resolve_auth_conflict();

    if (community_session_is_admin()) {
        return [
            'type'         => 'admin',
            'user_role'    => 'admin',
            'is_moderator' => true,
            'admin_id'     => (int) $_SESSION['admin_id'],
            'alumni_id'    => null,
            'display_name' => $_SESSION['admin_name'] ?? 'Administrator',
        ];
    }
    if (community_session_is_alumni()) {
        return [
            'type'         => 'alumni',
            'user_role'    => 'alumni',
            'is_moderator' => false,
            'admin_id'     => null,
            'alumni_id'    => (int) $_SESSION['alumni_id'],
            'display_name' => $_SESSION['alumni_name'] ?? 'Alumni',
        ];
    }
    return null;
}

function community_require_viewer(): array
{
    $v = community_current_viewer();
    if (!$v) {
        header('Location: index.php');
        exit();
    }
    return $v;
}

function community_ensure_csrf(): string
{
    if (empty($_SESSION['community_csrf'])) {
        $_SESSION['community_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['community_csrf'];
}

function community_verify_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['community_csrf']) && hash_equals($_SESSION['community_csrf'], $token);
}

function community_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function community_format_time(string $dt): string
{
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    return date('M j, Y · g:i A', $ts);
}

/** Batch-load alumni names by id */
function community_map_alumni_names(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, name FROM alumni WHERE id IN ($ph)");
    $st->execute($ids);
    $out = [];
    while ($r = $st->fetch()) {
        $out[(int) $r['id']] = $r['name'];
    }
    return $out;
}

/**
 * Batch-load alumni name + profile_photo by id.
 * Returns [ id => ['name' => string, 'photo' => string|null] ]
 */
function community_map_alumni_data(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, name, profile_photo FROM alumni WHERE id IN ($ph)");
    $st->execute($ids);
    $out = [];
    while ($r = $st->fetch()) {
        $out[(int) $r['id']] = ['name' => (string) $r['name'], 'photo' => $r['profile_photo'] ?: null];
    }
    return $out;
}

/**
 * Render a small circular avatar for a community post author.
 * Falls back to coloured initials if no photo or photo file is missing.
 */
function community_avatar_html(?string $photoPath, string $name, string $size = '38px'): string
{
    if ($photoPath && file_exists(__DIR__ . '/' . $photoPath)) {
        $url = htmlspecialchars($photoPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<img src="' . $url . '" alt="" style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;flex-shrink:0;vertical-align:middle;">';
    }
    // Coloured initials fallback
    $parts    = preg_split('/\s+/', trim($name)) ?: ['?'];
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    $palette = ['#2e7d32','#1565c0','#6a1b9a','#e65100','#37474f'];
    $bg      = $palette[abs(crc32($name)) % count($palette)];
    $fs      = (intval($size) < 40) ? '12px' : '15px';
    $escaped = htmlspecialchars($initials, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<div style="width:' . $size . ';height:' . $size . ';border-radius:50%;background:' . $bg . ';color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:' . $fs . ';font-weight:700;flex-shrink:0;vertical-align:middle;">' . $escaped . '</div>';
}

/** Batch-load admin names by id */
function community_map_admin_names(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, name FROM admins WHERE id IN ($ph)");
    $st->execute($ids);
    $out = [];
    while ($r = $st->fetch()) {
        $out[(int) $r['id']] = $r['name'];
    }
    return $out;
}

/**
 * Batch-load admin name + profile_photo by id.
 * Returns [ id => ['name' => string, 'photo' => string|null] ]
 */
function community_map_admin_data(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    // Tolerate missing columns (admins migration not applied yet)
    try {
        $st = $pdo->prepare("SELECT id, name, profile_photo FROM admins WHERE id IN ($ph)");
        $st->execute($ids);
        $out = [];
        while ($r = $st->fetch()) {
            $out[(int) $r['id']] = ['name' => (string) $r['name'], 'photo' => $r['profile_photo'] ?? null];
        }
        return $out;
    } catch (Throwable $e) {
        $names = community_map_admin_names($pdo, $ids);
        $out = [];
        foreach ($names as $id => $name) {
            $out[$id] = ['name' => (string) $name, 'photo' => null];
        }
        return $out;
    }
}

function community_author_label(string $authorType, ?int $adminId, ?int $alumniId, array $adminNames, array $alumniNames): string
{
    if ($authorType === 'admin' && $adminId) {
        return $adminNames[$adminId] ?? 'Staff';
    }
    if ($authorType === 'alumni' && $alumniId) {
        return $alumniNames[$alumniId] ?? 'Alumni';
    }
    return 'Member';
}

/** Delete post: admins (isset admin_id) may delete any; alumni only their own alumni-authored rows. */
function community_authorizes_post_delete(array $post): bool
{
    if (isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0) {
        return true;
    }
    return isset($_SESSION['alumni_id'])
        && $post['author_type'] === 'alumni'
        && (int) $post['author_alumni_id'] === (int) $_SESSION['alumni_id'];
}

/** Delete comment: same rule as posts. */
function community_authorizes_comment_delete(array $comment): bool
{
    if (isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0) {
        return true;
    }
    return isset($_SESSION['alumni_id'])
        && $comment['author_type'] === 'alumni'
        && (int) $comment['author_alumni_id'] === (int) $_SESSION['alumni_id'];
}

/** Remove repost row: admins any; alumni only own repost. */
function community_authorizes_repost_delete(array $repost): bool
{
    if (isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0) {
        return true;
    }
    return isset($_SESSION['alumni_id'])
        && $repost['reposter_type'] === 'alumni'
        && (int) $repost['reposter_alumni_id'] === (int) $_SESSION['alumni_id'];
}

/** UI: admins may delete any post; alumni only their own alumni-authored posts. */
function community_can_delete_post(array $viewer, array $post): bool
{
    return community_authorizes_post_delete($post);
}

/** UI: admins may delete any comment; alumni only their own alumni-authored comments. */
function community_can_delete_comment(array $viewer, array $comment): bool
{
    return community_authorizes_comment_delete($comment);
}

function community_can_delete_repost(array $viewer, array $repost): bool
{
    return community_authorizes_repost_delete($repost);
}

/**
 * @return array{items: list<array>, posts: array<int,array>, comments_by_post: array<int,list>}
 */
function community_build_feed(PDO $pdo, array $viewer, int $limit = 36): array
{
    $limPosts = max(10, min(80, $limit));
    $limRe    = max(10, min(80, $limit));

    $posts = $pdo->query("SELECT * FROM community_posts ORDER BY created_at DESC LIMIT $limPosts")->fetchAll();
    $reposts = $pdo->query("SELECT * FROM community_reposts ORDER BY created_at DESC LIMIT $limRe")->fetchAll();

    $items = [];
    foreach ($posts as $p) {
        $items[] = ['kind' => 'post', 'at' => $p['created_at'], 'post_id' => (int) $p['id'], 'post' => $p, 'repost' => null];
    }
    foreach ($reposts as $r) {
        $items[] = ['kind' => 'repost', 'at' => $r['created_at'], 'post_id' => (int) $r['original_post_id'], 'post' => null, 'repost' => $r];
    }
    usort($items, static function ($a, $b) {
        return strcmp($b['at'], $a['at']);
    });
    $items = array_slice($items, 0, $limit);

    $postRows = [];
    foreach ($items as $it) {
        $pid = $it['post_id'];
        if ($it['kind'] === 'post' && $it['post']) {
            $postRows[$pid] = $it['post'];
        }
    }
    $missing = [];
    foreach ($items as $it) {
        $pid = $it['post_id'];
        if (!isset($postRows[$pid])) {
            $missing[$pid] = true;
        }
    }
    if ($missing) {
        $ids = array_keys($missing);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $pdo->prepare("SELECT * FROM community_posts WHERE id IN ($ph)");
        $st->execute($ids);
        while ($row = $st->fetch()) {
            $postRows[(int) $row['id']] = $row;
        }
    }

    $postIds = array_keys($postRows);
    sort($postIds);

    $likeCounts = [];
    $commentCounts = [];
    $repostCounts = [];
    $likedSet = [];
    $repostedSet = [];

    if ($postIds) {
        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $st = $pdo->prepare("SELECT post_id, COUNT(*) AS c FROM community_likes WHERE post_id IN ($ph) GROUP BY post_id");
        $st->execute($postIds);
        while ($r = $st->fetch()) {
            $likeCounts[(int) $r['post_id']] = (int) $r['c'];
        }

        $st = $pdo->prepare("SELECT post_id, COUNT(*) AS c FROM community_comments WHERE post_id IN ($ph) GROUP BY post_id");
        $st->execute($postIds);
        while ($r = $st->fetch()) {
            $commentCounts[(int) $r['post_id']] = (int) $r['c'];
        }

        $st = $pdo->prepare("SELECT original_post_id, COUNT(*) AS c FROM community_reposts WHERE original_post_id IN ($ph) GROUP BY original_post_id");
        $st->execute($postIds);
        while ($r = $st->fetch()) {
            $repostCounts[(int) $r['original_post_id']] = (int) $r['c'];
        }

        if ($viewer['type'] === 'admin' && $viewer['admin_id']) {
            $st = $pdo->prepare("SELECT post_id FROM community_likes WHERE post_id IN ($ph) AND liker_type='admin' AND liker_admin_id = ?");
            $st->execute(array_merge($postIds, [$viewer['admin_id']]));
        } else {
            $st = $pdo->prepare("SELECT post_id FROM community_likes WHERE post_id IN ($ph) AND liker_type='alumni' AND liker_alumni_id = ?");
            $st->execute(array_merge($postIds, [$viewer['alumni_id']]));
        }
        while ($r = $st->fetch()) {
            $likedSet[(int) $r['post_id']] = true;
        }

        if ($viewer['type'] === 'admin' && $viewer['admin_id']) {
            $st = $pdo->prepare("SELECT original_post_id FROM community_reposts WHERE original_post_id IN ($ph) AND reposter_type='admin' AND reposter_admin_id = ?");
            $st->execute(array_merge($postIds, [$viewer['admin_id']]));
        } else {
            $st = $pdo->prepare("SELECT original_post_id FROM community_reposts WHERE original_post_id IN ($ph) AND reposter_type='alumni' AND reposter_alumni_id = ?");
            $st->execute(array_merge($postIds, [$viewer['alumni_id']]));
        }
        while ($r = $st->fetch()) {
            $repostedSet[(int) $r['original_post_id']] = true;
        }
    }

    $commentsByPost = [];
    if ($postIds) {
        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $st = $pdo->prepare("SELECT * FROM community_comments WHERE post_id IN ($ph) ORDER BY created_at ASC, id ASC");
        $st->execute($postIds);
        while ($c = $st->fetch()) {
            $pid = (int) $c['post_id'];
            if (!isset($commentsByPost[$pid])) {
                $commentsByPost[$pid] = [];
            }
            $commentsByPost[$pid][] = $c;
        }
    }

    return [
        'items'            => $items,
        'posts'            => $postRows,
        'comments_by_post' => $commentsByPost,
        'meta'             => [
            'like_counts'    => $likeCounts,
            'comment_counts' => $commentCounts,
            'repost_counts'  => $repostCounts,
            'liked'          => $likedSet,
            'reposted'       => $repostedSet,
        ],
    ];
}

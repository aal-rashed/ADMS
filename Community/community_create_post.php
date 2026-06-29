<?php
/**
 * Create community post (multipart) — PDO prepared statements.
 * Called via fetch(FormData) from community.php
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adms_session.php';
$portalHint = $_POST['portal'] ?? '';
$portalHint = in_array($portalHint, ['admin', 'alumni'], true) ? $portalHint : null;

// Admin portal uses the default PHP session (PHPSESSID), not the split ADMS_ADMIN cookie.
// Alumni portal uses the split ADMS_ALUMNI cookie via adms_session_start_community.
if ($portalHint === 'admin') {
    session_start();
} else {
    adms_session_start_community($portalHint);
    // Fallback: if no alumni split-session login is present but the admin (default PHP) session has admin_id, switch to it
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!community_verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token. Refresh the page.']);
    exit;
}

$body    = trim((string) ($_POST['body'] ?? ''));
$linkUrl = trim((string) ($_POST['link_url'] ?? ''));

$upload_dir = 'uploads/community/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$image_path = null;
if (!empty($_FILES['image']['name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed_exts, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP.']);
        exit;
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Image too large (max 5MB).']);
        exit;
    }
    $fname      = uniqid('com_', true) . '.' . $ext;
    $image_path = $upload_dir . $fname;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Failed to upload image.']);
        exit;
    }
}

if ($linkUrl !== '') {
    if (!filter_var($linkUrl, FILTER_VALIDATE_URL)) {
        if ($image_path && is_file($image_path)) {
            @unlink($image_path);
        }
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please enter a valid URL (including http:// or https://).']);
        exit;
    }
}

if ($body === '' && $linkUrl === '' && !$image_path) {
    if ($image_path && is_file($image_path)) {
        @unlink($image_path);
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Add some text, a link, or a photo to publish.']);
    exit;
}

try {
    if ($viewer['type'] === 'admin') {
        $st = $pdo->prepare('INSERT INTO community_posts (author_type, author_admin_id, author_alumni_id, body, link_url, image_path) VALUES (?,?,NULL,?,?,?)');
        $st->execute(['admin', $viewer['admin_id'], $body !== '' ? $body : null, $linkUrl !== '' ? $linkUrl : null, $image_path]);
    } else {
        $st = $pdo->prepare('INSERT INTO community_posts (author_type, author_admin_id, author_alumni_id, body, link_url, image_path) VALUES (?,NULL,?,?,?,?)');
        $st->execute(['alumni', $viewer['alumni_id'], $body !== '' ? $body : null, $linkUrl !== '' ? $linkUrl : null, $image_path]);
    }
    echo json_encode(['ok' => true, 'post_id' => (int) $pdo->lastInsertId()]);
} catch (Throwable $e) {
    if ($image_path && is_file($image_path)) {
        @unlink($image_path);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save post.']);
}

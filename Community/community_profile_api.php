<?php
/**
 * Community Profile API — handles profile photo uploads + bio updates
 * for BOTH alumni and admin accounts.
 * Each user may only update their own profile.
 */
header('Content-Type: application/json; charset=utf-8');

$portalPick = $_POST['portal'] ?? ($_SERVER['HTTP_X_ADMS_PORTAL'] ?? '');
$portalPick = in_array($portalPick, ['admin', 'alumni'], true) ? $portalPick : null;

require_once __DIR__ . '/adms_session.php';

// Admin portal uses the default PHP session (PHPSESSID), not the split ADMS_ADMIN cookie.
// Alumni portal uses the split ADMS_ALUMNI cookie via adms_session_start_community.
if ($portalPick === 'admin') {
    session_start();
} else {
    adms_session_start_community($portalPick);
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
    echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
    exit;
}

$viewer = community_current_viewer();
if (!$viewer) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$csrf   = trim($_POST['csrf']   ?? '');

if (!community_verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token. Refresh the page.']);
    exit;
}

if ($action !== 'update_profile') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

/* ------------------------------------------------------------------
 * Determine target role + id (must match the signed-in user)
 * ------------------------------------------------------------------ */
$role = $_POST['role'] ?? $viewer['type'];
$role = in_array($role, ['admin', 'alumni'], true) ? $role : $viewer['type'];

if ($role !== $viewer['type']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You may only edit your own profile.']);
    exit;
}

$userId = $role === 'admin' ? (int) $viewer['admin_id'] : (int) $viewer['alumni_id'];
if ($userId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.']);
    exit;
}

$table = $role === 'admin' ? 'admins' : 'alumni';

/* ------------------------------------------------------------------
 * Bio
 * ------------------------------------------------------------------ */
$bio = trim($_POST['bio'] ?? '');
if (mb_strlen($bio) > 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bio must be 500 characters or fewer.']);
    exit;
}

/* ------------------------------------------------------------------
 * Optional photo upload
 * ------------------------------------------------------------------ */
$photoPath = null;

if (!empty($_FILES['profile_photo']['name'])) {
    $file = $_FILES['profile_photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Upload error (code ' . (int)$file['error'] . ').']);
        exit;
    }

    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Only JPG, PNG, GIF, or WEBP images are allowed.']);
        exit;
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Photo must be smaller than 4 MB.']);
        exit;
    }

    $dir = __DIR__ . '/uploads/profile_photos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $ext      = $allowed[$mime];
    $filename = $role . '_' . $userId . '_' . time() . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save uploaded photo.']);
        exit;
    }

    $photoPath = 'uploads/profile_photos/' . $filename;

    // Delete the previous photo (if any)
    try {
        $old = $pdo->prepare("SELECT profile_photo FROM $table WHERE id = ?");
        $old->execute([$userId]);
        $oldRow = $old->fetch();
        if (!empty($oldRow['profile_photo'])) {
            $oldFile = __DIR__ . '/' . $oldRow['profile_photo'];
            if (is_file($oldFile)) @unlink($oldFile);
        }
    } catch (Throwable $e) { /* columns may not exist yet — ignore */ }
}

/* ------------------------------------------------------------------
 * Persist
 * ------------------------------------------------------------------ */
try {
    if ($photoPath !== null) {
        $st = $pdo->prepare("UPDATE $table SET bio = ?, profile_photo = ? WHERE id = ?");
        $st->execute([$bio, $photoPath, $userId]);
    } else {
        $st = $pdo->prepare("UPDATE $table SET bio = ? WHERE id = ?");
        $st->execute([$bio, $userId]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database error. Make sure the schema migration for ' . $table . ' has been imported (community_profile_schema.sql for alumni, community_profile_admin_schema.sql for admins).',
    ]);
    exit;
}

echo json_encode([
    'ok'        => true,
    'photo_url' => $photoPath,
    'bio'       => $bio,
]);

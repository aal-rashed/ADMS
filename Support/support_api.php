<?php
/**
 * Support DM API — send / fetch messages.
 * Handles both portals (alumni + admin) using the same split-session pattern
 * as community_api.php.
 *
 * Actions:
 *   - send         : alumni or admin posts a new message in a thread
 *   - fetch_thread : poll for the latest messages in a thread
 *   - mark_read    : explicit read-receipt (also done on thread open)
 */
header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$portalPick = $data['portal'] ?? ($_SERVER['HTTP_X_ADMS_PORTAL'] ?? '');
$portalPick = in_array($portalPick, ['admin', 'alumni'], true) ? $portalPick : null;

require_once __DIR__ . '/adms_session.php';

// Same session-start pattern as community_api.php:
//   - admin portal  → default PHPSESSID
//   - alumni portal → split ADMS_ALUMNI cookie
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
require __DIR__ . '/support_lib.php';

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

if (!support_table_ready($pdo)) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'Support table not installed. Run support_messages_schema.sql.',
    ]);
    exit;
}

// Identify the caller — either alumni or admin
$isAdmin  = !empty($_SESSION['admin_id'])  && (int) $_SESSION['admin_id']  > 0;
$isAlumni = !empty($_SESSION['alumni_id']) && (int) $_SESSION['alumni_id'] > 0;

if (!$isAdmin && !$isAlumni) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$action = $data['action'] ?? '';
$csrf   = $data['csrf']   ?? '';

if (!support_csrf_verify($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token. Refresh the page.']);
    exit;
}

try {
    switch ($action) {

        /* ----------------------------------------------------------
         * Send a new message.
         *   Alumni sends → into their own thread.
         *   Admin  sends → into whichever thread (alumni_id from payload).
         * ---------------------------------------------------------- */
        case 'send': {
            $body = trim((string) ($data['body'] ?? ''));
            if ($body === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Message cannot be empty.']);
                exit;
            }
            if (mb_strlen($body) > 4000) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Message is too long (max 4000 characters).']);
                exit;
            }

            if ($isAlumni) {
                $alumniId = (int) $_SESSION['alumni_id'];
                $st = $pdo->prepare(
                    "INSERT INTO support_messages
                       (alumni_id, sender_type, sender_admin_id, body, is_read_by_admin, is_read_by_alumni)
                     VALUES (?, 'alumni', NULL, ?, 0, 1)"
                );
                $st->execute([$alumniId, $body]);
            } else {
                // admin
                $alumniId = (int) ($data['alumni_id'] ?? 0);
                if ($alumniId <= 0) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'error' => 'Missing alumni id.']);
                    exit;
                }
                // confirm alumni exists
                $chk = $pdo->prepare("SELECT id FROM alumni WHERE id = ? LIMIT 1");
                $chk->execute([$alumniId]);
                if (!$chk->fetch()) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'Alumni not found.']);
                    exit;
                }
                $adminId = (int) $_SESSION['admin_id'];
                $st = $pdo->prepare(
                    "INSERT INTO support_messages
                       (alumni_id, sender_type, sender_admin_id, body, is_read_by_admin, is_read_by_alumni)
                     VALUES (?, 'admin', ?, ?, 1, 0)"
                );
                $st->execute([$alumniId, $adminId, $body]);
            }

            $newId = (int) $pdo->lastInsertId();
            $st = $pdo->prepare("SELECT id, alumni_id, sender_type, sender_admin_id, body, created_at FROM support_messages WHERE id = ?");
            $st->execute([$newId]);
            $row = $st->fetch();

            echo json_encode([
                'ok'      => true,
                'message' => [
                    'id'              => (int) $row['id'],
                    'alumni_id'       => (int) $row['alumni_id'],
                    'sender_type'     => $row['sender_type'],
                    'sender_admin_id' => $row['sender_admin_id'] ? (int) $row['sender_admin_id'] : null,
                    'body'            => $row['body'],
                    'created_at'      => $row['created_at'],
                    'stamp'           => support_msg_stamp($row['created_at']),
                ],
            ]);
            exit;
        }

        /* ----------------------------------------------------------
         * Fetch the entire thread (used for polling).
         * Optional 'after_id' — only return messages with id > after_id.
         * ---------------------------------------------------------- */
        case 'fetch_thread': {
            $alumniId = $isAlumni
                ? (int) $_SESSION['alumni_id']
                : (int) ($data['alumni_id'] ?? 0);

            if ($alumniId <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Missing alumni id.']);
                exit;
            }

            $afterId = (int) ($data['after_id'] ?? 0);
            if ($afterId > 0) {
                $st = $pdo->prepare(
                    "SELECT id, sender_type, sender_admin_id, body, created_at
                     FROM support_messages
                     WHERE alumni_id = ? AND id > ?
                     ORDER BY id ASC"
                );
                $st->execute([$alumniId, $afterId]);
            } else {
                $st = $pdo->prepare(
                    "SELECT id, sender_type, sender_admin_id, body, created_at
                     FROM support_messages
                     WHERE alumni_id = ?
                     ORDER BY id ASC"
                );
                $st->execute([$alumniId]);
            }
            $rows = $st->fetchAll();

            // Mark thread as read for whichever side is viewing it
            support_mark_read($pdo, $alumniId, $isAdmin ? 'admin' : 'alumni');

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id'              => (int) $r['id'],
                    'sender_type'     => $r['sender_type'],
                    'sender_admin_id' => $r['sender_admin_id'] ? (int) $r['sender_admin_id'] : null,
                    'body'            => $r['body'],
                    'created_at'      => $r['created_at'],
                    'stamp'           => support_msg_stamp($r['created_at']),
                ];
            }
            echo json_encode(['ok' => true, 'messages' => $out]);
            exit;
        }

        /* ----------------------------------------------------------
         * Mark a thread as read (manual call).
         * ---------------------------------------------------------- */
        case 'mark_read': {
            $alumniId = $isAlumni
                ? (int) $_SESSION['alumni_id']
                : (int) ($data['alumni_id'] ?? 0);
            if ($alumniId <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Missing alumni id.']);
                exit;
            }
            support_mark_read($pdo, $alumniId, $isAdmin ? 'admin' : 'alumni');
            echo json_encode(['ok' => true]);
            exit;
        }

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}

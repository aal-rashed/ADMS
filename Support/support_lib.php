<?php
/**
 * Support module — helpers for the Alumni ↔ Admin direct-message channel.
 * Mirrors the patterns used by community_lib.php (PDO + prepared statements).
 */

/**
 * Confirm the support_messages table is installed.
 */
function support_table_ready(?PDO $pdo): bool
{
    if (!$pdo) {
        return false;
    }
    try {
        $n = (int) $pdo->query(
            "SELECT COUNT(*) AS c FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'support_messages'"
        )->fetch()['c'];
        return $n === 1;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * HTML escape helper (no double encoding mishaps).
 */
function support_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a timestamp as a friendly "time ago" string.
 */
function support_time_ago(string $datetime): string
{
    try {
        $now  = new DateTime();
        $then = new DateTime($datetime);
    } catch (Throwable $e) {
        return $datetime;
    }
    $diff = $now->getTimestamp() - $then->getTimestamp();
    if ($diff < 60)        return 'just now';
    if ($diff < 3600)      return floor($diff / 60) . 'm ago';
    if ($diff < 86400)     return floor($diff / 3600) . 'h ago';
    if ($diff < 86400 * 7) return floor($diff / 86400) . 'd ago';
    return $then->format('M j, Y');
}

/**
 * Format a timestamp for the chat-bubble (short HH:MM with date when not today).
 */
function support_msg_stamp(string $datetime): string
{
    try {
        $then = new DateTime($datetime);
        $now  = new DateTime();
    } catch (Throwable $e) {
        return $datetime;
    }
    $isToday = $then->format('Y-m-d') === $now->format('Y-m-d');
    return $isToday ? $then->format('g:i A') : $then->format('M j, g:i A');
}

/**
 * CSRF token for the support module (kept independent of community's token).
 */
function support_csrf_token(): string
{
    if (empty($_SESSION['support_csrf'])) {
        $_SESSION['support_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['support_csrf'];
}

function support_csrf_verify(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['support_csrf'])
        && hash_equals($_SESSION['support_csrf'], (string) $token);
}

/**
 * Count unread messages for a given side.
 *   - 'admin'  : messages from alumni that no admin has opened yet
 *   - 'alumni' : messages addressed to a specific alumni
 */
function support_count_unread_for_admin(PDO $pdo): int
{
    try {
        $st = $pdo->query(
            "SELECT COUNT(*) AS c FROM support_messages
             WHERE sender_type = 'alumni' AND is_read_by_admin = 0"
        );
        return (int) $st->fetch()['c'];
    } catch (Throwable $e) {
        return 0;
    }
}

function support_count_unread_for_alumni(PDO $pdo, int $alumniId): int
{
    if ($alumniId <= 0) return 0;
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM support_messages
             WHERE alumni_id = ? AND sender_type = 'admin' AND is_read_by_alumni = 0"
        );
        $st->execute([$alumniId]);
        return (int) $st->fetch()['c'];
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Fetch all messages in one alumni's thread, oldest first.
 */
function support_fetch_thread(PDO $pdo, int $alumniId): array
{
    if ($alumniId <= 0) return [];
    $st = $pdo->prepare(
        "SELECT id, alumni_id, sender_type, sender_admin_id, body,
                is_read_by_admin, is_read_by_alumni, created_at
         FROM support_messages
         WHERE alumni_id = ?
         ORDER BY created_at ASC, id ASC"
    );
    $st->execute([$alumniId]);
    return $st->fetchAll();
}

/**
 * Inbox for admins — one row per alumni thread, latest message + unread count.
 */
function support_admin_inbox(PDO $pdo, string $search = ''): array
{
    $search = trim($search);
    $params = [];
    $where  = '';
    if ($search !== '') {
        $where = "WHERE a.name LIKE ? OR a.student_id LIKE ?";
        $like  = "%{$search}%";
        $params = [$like, $like];
    }
    // Join the latest message per alumni with the alumni record.
    $sql = "
        SELECT
            a.id             AS alumni_id,
            a.name           AS alumni_name,
            a.student_id     AS alumni_sid,
            a.major          AS alumni_major,
            sm.body          AS last_body,
            sm.sender_type   AS last_sender,
            sm.created_at    AS last_time,
            (SELECT COUNT(*) FROM support_messages u
              WHERE u.alumni_id = a.id
                AND u.sender_type = 'alumni'
                AND u.is_read_by_admin = 0)  AS unread_count
        FROM alumni a
        INNER JOIN (
            SELECT alumni_id, MAX(id) AS max_id
            FROM support_messages
            GROUP BY alumni_id
        ) latest ON latest.alumni_id = a.id
        INNER JOIN support_messages sm ON sm.id = latest.max_id
        {$where}
        ORDER BY sm.created_at DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Load one alumni row (used by admin to show conversation header).
 */
function support_load_alumni(PDO $pdo, int $alumniId): ?array
{
    if ($alumniId <= 0) return null;
    $st = $pdo->prepare(
        "SELECT id, name, student_id, major, graduation_term, academic_degree
         FROM alumni WHERE id = ?"
    );
    $st->execute([$alumniId]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Mark a thread as read by one side.
 */
function support_mark_read(PDO $pdo, int $alumniId, string $side): void
{
    if ($alumniId <= 0) return;
    if ($side === 'admin') {
        $st = $pdo->prepare(
            "UPDATE support_messages
             SET is_read_by_admin = 1
             WHERE alumni_id = ? AND sender_type = 'alumni' AND is_read_by_admin = 0"
        );
        $st->execute([$alumniId]);
    } elseif ($side === 'alumni') {
        $st = $pdo->prepare(
            "UPDATE support_messages
             SET is_read_by_alumni = 1
             WHERE alumni_id = ? AND sender_type = 'admin' AND is_read_by_alumni = 0"
        );
        $st->execute([$alumniId]);
    }
}

# Support Module

This folder contains the **help-desk messaging** feature of ADMS — a two-way communication channel between alumni and admin staff for handling queries and assistance.

---

## Responsibilities

- Allow alumni to send support messages to the admin team.
- Allow admins to read and reply to alumni messages.
- Track read/unread status and display unread badges in navigation.
- Expose an API endpoint for dynamic support interactions and unread count polling.

---

## File List

| File | Purpose |
|------|---------|
| `alumni_support.php` | Alumni support inbox — shows their message thread and a form to send a new message |
| `admin_support.php` | Admin support inbox — lists all alumni threads with unread indicators and reply interface |
| `support_api.php` | API endpoint for support actions (send message, mark read, get unread count) |
| `support_lib.php` | Shared library functions used by support pages and the API |

---

## Database Table

See `Database/support_messages_schema.sql` for the full definition.

```sql
CREATE TABLE support_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id   INT NOT NULL,
    sender_type ENUM('alumni', 'admin') NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES alumni(alumni_id)
);
```

`alumni_id` groups all messages into a single thread per alumni — alternating between alumni and admin messages.

---

## Database Access

```php
require_once '../config.php';
// $conn (mysqli) and $pdo are now available
```

---

## `support_lib.php` — Shared Functions

Common logic extracted here to avoid duplication between `alumni_support.php`, `admin_support.php`, and `support_api.php`:

- `get_thread($pdo, $alumni_id)` — fetch all messages in a thread ordered by `created_at ASC`
- `send_message($pdo, $alumni_id, $sender_type, $message)` — insert a new message
- `mark_thread_read($pdo, $alumni_id, $reader_type)` — mark all unread messages as read
- `get_unread_count($pdo)` — return count of unread alumni messages (for admin badge)

---

## `support_api.php` — API Endpoint

Handles dynamic requests from the frontend via `fetch()`. Accepts an `action` parameter:

```
GET  support_api.php?action=unread_count   → { "count": N }
POST support_api.php                       → send message / mark read
```

Used by the unread badge polling in `Frontend-UI/index.php`:

```javascript
fetch('../Support/support_api.php?action=unread_count')
    .then(r => r.json())
    .then(data => {
        document.querySelector('.badge-unread').textContent = data.count || '';
    });
```

Polling interval: **15 seconds**.

---

## Message Thread Query

```php
$stmt = $pdo->prepare("
    SELECT * FROM support_messages
    WHERE alumni_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$alumni_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

## Access Control

| Action | Allowed Role |
|--------|-------------|
| Send message | Alumni only |
| View own thread | Alumni (own thread only) |
| View all threads | Admin only |
| Reply | Admin only |

All POST actions validate session and CSRF token before processing.

---

## Notes

- Each alumni has a **single thread** — there is no multi-ticket system in this version.
- Messages are never deleted from the database; the full history is always visible.
- `support_api.php` always returns `Content-Type: application/json`.
- For future work: consider WebSockets or Server-Sent Events to replace the polling approach.

# Admin Portal

This folder contains all pages and logic for the **ADMS Admin Portal** — the staff-facing side of the Alumni Data Management System.

---

## Responsibilities

- Authenticate admin users and maintain their session.
- Display the admin dashboard with summary statistics.
- Manage alumni records (add, edit, delete, export, import from Excel).
- Publish and manage announcements.
- Respond to alumni support messages.

---

## File List

| File | Purpose |
|------|---------|
| `dashboard.php` | Main landing page after admin login — shows record counts and quick links |
| `add_alumni.php` | Form to manually add a new alumni record |
| `edit_alumni.php` | Form to edit an existing alumni record |
| `delete_alumni.php` | Handles alumni record deletion (POST only) |
| `import_alumni.php` | Handles Excel upload and Arabic-normalized import pipeline |
| `export_alumni.php` | Exports alumni data (PDF / Excel) |
| `edit_announcement.php` | Form to create or edit an announcement |
| `adms_session.php` | Admin session helper — included by all Admin portal pages |
| `sidebar.php` | Shared sidebar navigation partial (included via `require`) |

---

## Session Handling

The Admin portal uses `adms_session.php` for session management:

```php
require_once 'adms_session.php';
// Auth check at the top of every protected page:
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../Auth/admin_login.php');
    exit();
}
```

Do **not** mix this with the Alumni portal's session pattern.

---

## Database Access

All pages include the shared config file from the project root:

```php
require_once '../config.php';
// $conn (mysqli) and $pdo are now available
```

---

## Upload Path Convention

When handling file uploads (e.g., announcement images):

```php
// Physical write path
$upload_dir = __DIR__ . '/../uploads/announcements/';

// URL-relative path stored in the database
$db_path = 'uploads/announcements/' . $filename;
```

---

## Styling

Admin pages import shared styles from the `Frontend-UI/` folder:

```html
<link rel="stylesheet" href="../Frontend-UI/style.css">
```

CSS custom properties (`var(--green)`, `var(--border)`, etc.) are used throughout — do not hardcode color values.

---

## Notes

- `sidebar.php` is included as a partial in every admin page — do not duplicate navigation HTML per page.
- Excel import in `import_alumni.php` includes Arabic letter-variant normalization (أ/إ/آ → ا, ة → ه) before inserting into the database.
- All destructive actions (delete, import overwrite) are POST-only with CSRF token validation.

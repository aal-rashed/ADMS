# Announcements Module

This folder contains the pages and handlers for the **Announcements** feature in ADMS — covering both the admin management side and the alumni viewing side.

---

## Responsibilities

- Allow admins to create and edit announcements.
- Display published announcements to alumni with type filtering and image zoom.
- Store announcement data and associated images in the database and file system.

---

## File List

| File | Purpose |
|------|---------|
| `announcements.php` | Admin announcements management page — list, create, edit, delete |
| `add_announcement.php` | Handler or form for adding a new announcement |
| `alumni_announcements.php` | Alumni-facing announcement list/feed page |
| `alumni_announcement_view.php` | Single announcement detail view for alumni (with image zoom) |

---

## Database Table

```sql
CREATE TABLE announcements (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255)    NOT NULL,
    body        TEXT            NOT NULL,
    type        VARCHAR(50),
    image_path  VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

See `Database/announcements_schema.sql` for the full schema.

---

## Database Access

```php
require_once '../config.php';
// $conn (mysqli) and $pdo are now available
```

---

## Image Upload Convention

Images are stored outside the `Announcements/` folder to keep them web-accessible:

```php
// Physical path for writing the file
$upload_dir = __DIR__ . '/../uploads/announcements/';

// Relative URL path stored in the DB (used in <img src="...">)
$db_path = 'uploads/announcements/' . $filename;
```

Never store the absolute server path in the database — this breaks display when the project is moved.

---

## Type Filtering

Announcements support a `type` field for client-side filtering. The alumni view uses JavaScript to show/hide cards by type without a page reload:

```javascript
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const type = btn.dataset.type;
        document.querySelectorAll('.announcement-card').forEach(card => {
            card.style.display = (type === 'all' || card.dataset.type === type) ? '' : 'none';
        });
    });
});
```

---

## Image Zoom

Announcement images support click-to-zoom via a lightweight overlay defined in `Frontend-UI/style.css` and `Frontend-UI/index.php`:

```html
<img src="<?= $db_path ?>" class="announcement-img" onclick="openZoom(this.src)">
```

---

## Access Control

| Action | Allowed Role |
|--------|-------------|
| Create / Edit / Delete | Admin only |
| View list and detail | Alumni (authenticated) |

---

## Notes

- Announcement queries always order by `created_at DESC` so the newest appears first.
- Deleting an announcement should also remove its image file from `uploads/announcements/` to avoid orphaned files.
- The `type` values used in filtering must stay consistent between the database and the filter buttons in the view template.

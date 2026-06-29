# Community Module

This folder contains the **Community** feature of ADMS — a portal-specific social feed where alumni can publish posts, leave comments, and view profiles. Admins have a parallel view with moderation controls.

---

## Responsibilities

- Display portal-specific post feeds (alumni feed vs. admin moderation view).
- Allow alumni to create posts and comments.
- Render individual post detail pages with comment threads.
- Display alumni and admin profile pages within the community context.
- Allow admins to moderate (delete) posts and comments.
- Expose API endpoints for dynamic community interactions.

---

## File List

| File | Purpose |
|------|---------|
| `community.php` | Community module entry point / router |
| `community_lib.php` | Shared library functions used across community pages |
| `community_api.php` | API endpoint for community actions (like, post, comment via fetch/AJAX) |
| `community_post.php` | Single post detail page (shared base) |
| `community_create_post.php` | Form and handler for creating a new post |
| `community_profile.php` | Alumni profile view within the community |
| `community_profile_api.php` | API endpoint for profile-related community data |
| `alumni_community.php` | Alumni-facing community post feed |
| `alumni_community_post.php` | Single post detail view for alumni (with comment form) |
| `alumni_community_profile.php` | Alumni profile page as seen by other alumni |
| `admin_community.php` | Admin-facing community feed with moderation controls |
| `admin_community_post.php` | Single post detail view for admin (with delete controls) |
| `admin_community_profile.php` | Alumni profile page as seen by admin |

---

## Portal Isolation

The Community module uses **portal-specific files** for alumni and admin views. This is intentional:

- `alumni_community*.php` files enforce `$_SESSION['alumni_id']` and show alumni actions (create post, comment).
- `admin_community*.php` files enforce `$_SESSION['admin_id']` and show moderation actions (delete post, delete comment).
- Shared logic is extracted into `community_lib.php` to avoid duplication.

---

## Database Tables

See `Database/community_schema.sql` and `Database/community_profile_schema.sql` for full definitions.

```sql
-- Posts
CREATE TABLE community_posts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id   INT NOT NULL,
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Comments
CREATE TABLE community_comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    author_id   INT NOT NULL,
    author_type ENUM('alumni', 'admin') NOT NULL,
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Database Access

```php
require_once '../config.php';
// $conn (mysqli) and $pdo are now available
```

API files (`community_api.php`, `community_profile_api.php`) return JSON responses:

```php
header('Content-Type: application/json');
echo json_encode($result);
```

---

## Access Control

| Action | Allowed Role |
|--------|-------------|
| View feed | Admin, Alumni |
| Create post | Alumni only |
| Comment | Alumni only |
| Delete post / comment | Admin only |

All delete actions are POST-only with CSRF token validation.

---

## Notes

- All user-submitted content must be escaped with `htmlspecialchars()` before rendering to prevent XSS.
- Shared logic (fetching posts, formatting dates, building author info) lives in `community_lib.php` — add new shared helpers there rather than duplicating across files.
- Profile pages expose only public-facing fields — sensitive data (phone, GPA, etc.) is not shown.

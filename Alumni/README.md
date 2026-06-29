# Alumni Portal

This folder contains all pages and logic for the **ADMS Alumni Portal** — the graduate-facing side of the Alumni Data Management System.

---

## Responsibilities

- Authenticate alumni users and maintain their session.
- Display a personalized dashboard after login.
- Allow alumni to view and update their own profile.
- Browse published announcements.
- Search for other alumni.
- Participate in the community and contact support.

---

## File List

| File | Purpose |
|------|---------|
| `alumni.php` | Alumni portal entry point / index |
| `alumni_dashboard.php` | Home page after login — shows recent announcements and quick links |
| `alumni_profile.php` | View and edit own alumni profile information |
| `alumni_search.php` | Search for other alumni by name or graduation year |
| `alumni_logout.php` | Destroys alumni session and redirects to login |
| `alumni_sidebar.php` | Shared sidebar navigation partial (included via `require`) |

---

## Session Handling

The Alumni portal uses a **custom session initializer** — do not use a plain `session_start()` here:

```php
require_once '../Auth/alumni_login.php'; // session is initialized at login
// Auth check at the top of every protected page:
if (!isset($_SESSION['alumni_id'])) {
    header('Location: ../Auth/alumni_login.php');
    exit();
}
```

This is intentionally separate from the Admin portal's session pattern. Mixing the two causes silent authentication failures.

---

## Database Access

All pages include the shared config file from the project root:

```php
require_once '../config.php';
// $conn (mysqli) and $pdo are now available
```

---

## Styling

Alumni pages import shared styles from the `Frontend-UI/` folder:

```html
<link rel="stylesheet" href="../Frontend-UI/style.css">
```

The mobile-responsive layout (off-canvas sidebar, hamburger menu) is implemented in shared CSS — do not duplicate it per page.

---

## Notes

- `alumni_sidebar.php` is included as a partial in every alumni page — do not duplicate navigation HTML per page.
- Alumni can only view and edit **their own** record — cross-account access is blocked server-side.
- Unread support message badges are updated via live polling against `Support/support_api.php`.
- All forms include **CSRF tokens** for security.

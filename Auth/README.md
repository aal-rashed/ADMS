# Auth Module

This folder handles **authentication** for both the Admin and Alumni portals — login forms, credential verification, session initialization, and logout.

---

## Responsibilities

- Render and process the login forms for both portals.
- Validate credentials against the database.
- Initialize the correct session for each portal.
- Redirect authenticated users to their dashboard.
- Provide the config template for local database setup.
- Handle logout for both portals.

---

## File List

| File | Purpose |
|------|---------|
| `admin_login.php` | Admin login form and POST handler |
| `alumni_login.php` | Alumni login form and POST handler |
| `logout.php` | Shared logout handler — destroys session and redirects to login |
| `config.example.php` | Safe demo config template — **copy to `../config.php`** and fill in local credentials |

> `config.php` itself is **not** in this folder and is **not** committed to GitHub. It lives at the project root and is listed in `.gitignore`.

---

## Session Architecture

ADMS uses **two separate session patterns** — one per portal. This is intentional and must not be mixed.

### Admin Session

```php
// Set after successful login in admin_login.php
session_start();
$_SESSION['admin_id'] = $admin['id'];
$_SESSION['admin_name'] = $admin['username'];
header('Location: ../Admin/dashboard.php');
```

### Alumni Session

```php
// Set after successful login in alumni_login.php
session_start();
$_SESSION['alumni_id'] = $alumni['alumni_id'];
$_SESSION['alumni_name'] = $alumni['full_name'];
header('Location: ../Alumni/alumni_dashboard.php');
```

> **Warning:** Both portals use `session_start()` but store different session keys (`admin_id` vs `alumni_id`). Always check which key is expected by the page you are working on.

---

## Config Template (`config.example.php`)

This file is committed to GitHub as a safe starting point. Copy it to the project root and rename it:

```bash
cp Auth/config.example.php config.php
```

Then open `config.php` and fill in your local XAMPP values:

```php
$servername = "localhost";
$username   = "root";       // default XAMPP username
$password   = "";           // default XAMPP password is empty
$dbname     = "adms_demo";  // must match the database you created in phpMyAdmin
```

---

## Login Flow

```
User submits form (POST)
    ↓
Validate CSRF token
    ↓
Query DB for matching credentials (password_verify against hashed password)
    ↓
  [Match]    → Set session variable → Redirect to dashboard
  [No match] → Show error message (do not reveal which field was wrong)
```

---

## Logout (`logout.php`)

The shared logout handler destroys the active session and redirects to the appropriate login page:

```php
session_start();
session_destroy();
header('Location: admin_login.php'); // or alumni_login.php depending on role
exit();
```

---

## Password Hashing

Passwords are stored as **bcrypt hashes**:

```php
// When creating/updating a password:
$hash = password_hash($plaintext, PASSWORD_DEFAULT);

// When verifying at login:
if (password_verify($plaintext, $hash)) { /* authenticated */ }
```

Never store or compare plain-text passwords.

---

## Notes

- Never expose which field (username vs. password) caused a login failure in the error message.
- Both portals share `logout.php` — the redirect destination is determined by which session key is active.
- All login forms include **CSRF tokens** for security.

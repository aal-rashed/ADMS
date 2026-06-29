# Database

This folder contains the **database schema files and demo seed data** for the Alumni Data Management System. Each module has its own schema file for clarity.

---

## Responsibilities

- Define all MySQL table structures for the application.
- Provide demo seed data for development and testing.
- Keep schema files modular — one file per module.

> The database **connection file** (`config.php`) lives at the **project root**, not in this folder. See the Configuration section below.

---

## File List

| File | Purpose | Committed to GitHub? |
|------|---------|----------------------|
| `admins.sql` | Admin accounts table schema + demo admin user | ✅ Yes |
| `alumni.sql` | Alumni table schema + demo records (no real student data) | ✅ Yes |
| `announcements_schema.sql` | Announcements table schema | ✅ Yes |
| `community_schema.sql` | Community posts and comments table schema | ✅ Yes |
| `community_profile_schema.sql` | Community profile extensions table schema | ✅ Yes |
| `community_profile_admin_schema.sql` | Admin profile table schema for community | ✅ Yes |
| `support_messages_schema.sql` | Support/help-desk messages table schema | ✅ Yes |

> Real student data exports (if any exist locally) must **never** be committed to GitHub and should be added to `.gitignore`.

---

## Database Setup

1. Open **phpMyAdmin** at `http://localhost/phpmyadmin`.
2. Create a new database (e.g. `adms_demo`).
3. Select the database, click **Import**, and import each `.sql` file in this order:

```
1. admins.sql
2. alumni.sql
3. announcements_schema.sql
4. community_schema.sql
5. community_profile_schema.sql
6. community_profile_admin_schema.sql
7. support_messages_schema.sql
```

Or via the MySQL CLI:

```bash
mysql -u root -p -e "CREATE DATABASE adms_demo;"
for f in admins.sql alumni.sql announcements_schema.sql community_schema.sql community_profile_schema.sql community_profile_admin_schema.sql support_messages_schema.sql; do
  mysql -u root -p adms_demo < Database/$f
done
```

---

## Configuration (`config.php`)

The connection file lives at the **project root** and is never committed to GitHub. Copy the template from the Auth folder:

```bash
cp Auth/config.example.php config.php
```

Then set your local values in `config.php`:

```php
$servername = "localhost";
$username   = "root";       // default XAMPP username
$password   = "";           // default XAMPP password is empty
$dbname     = "adms_demo";  // must match the database you created
```

Include it in any PHP file that needs database access:

```php
require_once '../config.php';
// Provides: $conn (mysqli) and $pdo (PDO)
```

---

## Core Tables

| Table | Schema File | Description |
|-------|-------------|-------------|
| `admins` | `admins.sql` | Admin user accounts |
| `alumni` | `alumni.sql` | Graduate records |
| `announcements` | `announcements_schema.sql` | Published announcements |
| `community_posts` | `community_schema.sql` | Alumni community posts |
| `community_comments` | `community_schema.sql` | Comments on posts |
| `community_profiles` | `community_profile_schema.sql` | Alumni community profile extensions |
| `community_admin_profiles` | `community_profile_admin_schema.sql` | Admin profiles for community |
| `support_messages` | `support_messages_schema.sql` | Help-desk messages |

---

## Arabic Data Notes

The `alumni` table stores Arabic text in **`utf8mb4`** collation. All tables and the connection use `charset=utf8mb4`.

During Excel import, Arabic letter variants are normalized **before** insertion:

| Variant | Normalized To |
|---------|--------------|
| أ, إ, آ | ا |
| ة | ه |

---

## Query Conventions

- All sensitive queries use **PDO prepared statements** via `$pdo`.
- The `$conn` mysqli connection is available for simpler queries.
- Timestamps default to `CURRENT_TIMESTAMP` — no manual timestamp insertion needed on INSERT.

---

## Notes

- The demo dataset contains fictional alumni records only — no real student data is included in this repository.
- For a production deployment, create a dedicated MySQL user with only SELECT, INSERT, UPDATE, DELETE permissions on the target database rather than using `root`.

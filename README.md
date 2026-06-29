# Alumni Data Management System (ADMS)

> A web-based platform for managing alumni records, announcements, community interaction, and support communication at the College of Computer (COC), Qassim University.

---

## Overview

The Alumni Data Management System (ADMS) is a full-stack web application that provides two dedicated portals:

- **Admin Portal** — for staff to manage alumni records, publish announcements, moderate community content, and handle support requests.
- **Alumni Portal** — for graduates to view their profile, browse announcements, participate in the community, and contact support.

ADMS was developed as a Final Year Project (FYP) under the supervision of **Dr. Daniyah Aloqalaa**, College of Computer, Qassim University.

---

## Team

| Name | GitHub |
|------|--------|
| Abdullah N. ALrashed | [@aal-rashed](https://github.com/aal-rashed) |
| Mohammed A. Aljamal | [@m-aljamal90](https://github.com/m-aljamal90) |
| Moath S. Alburaidi | [@ITmoath](https://github.com/ITmoath) |

**Supervisor:** Dr. Daniyah Aloqalaa — College of Computer, Qassim University

---

## Folder Structure

```
ADMS/
│
├── .gitignore              # Excludes config.php and real SQL data from GitHub
├── config.example.php      # Safe demo config template — copy to config.php
├── Admin/                  # Admin portal pages and logic
├── Alumni/                 # Alumni portal pages and logic
├── Announcements/          # Announcement management (admin) and viewing (alumni)
├── Auth/                   # Login, logout, and session handling for both portals
├── Community/              # Community posts, comments, and profile pages
├── Database/               # SQL schema, seed data, and database utilities
├── Frontend-UI/            # Shared CSS, JavaScript, images, and UI assets
└── Support/                # Help-desk messaging between alumni and admin
```

---

## Tech Stack

| Layer      | Technology                        |
|------------|-----------------------------------|
| Frontend   | HTML5, CSS3, JavaScript (Vanilla) |
| Backend    | PHP 8.x                           |
| Database   | MySQL 8.x                         |
| Server     | Apache via XAMPP (local dev)      |
| Dev Tools  | XAMPP, phpMyAdmin, VS Code        |

---

## Key Features

- **Alumni record management** — import from Excel, search, filter, and edit graduate data.
- **Arabic data support** — normalization of Arabic letter variants (أ/إ/آ, ة/ه) during Excel import.
- **Announcements** — rich announcements with image zoom, type filtering, and live database queries.
- **Community module** — portal-specific post feeds, post detail pages, and profile views.
- **Support / help-desk** — two-way messaging between alumni and admin with unread badges.
- **Mobile responsive** — off-canvas sidebar and hamburger menu across both portals.
- **Role-based access** — separate session handling for Admin and Alumni portals.

---

## Setup Instructions

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP)
- A modern web browser

### Installation

1. Clone the repository into your XAMPP `htdocs` directory:
   ```bash
   git clone https://github.com/aal-rashed/ADMS.git C:/xampp/htdocs/ADMS
   ```

2. Copy the config template and fill in your local XAMPP credentials:
   ```bash
   cp config.example.php config.php
   ```
   Then open `config.php` and set your local database name, username, and password.

3. Start **Apache** and **MySQL** from the XAMPP Control Panel.

4. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), create a new database (e.g. `adms_demo`), then import the schema:
   ```
   Database/schema.sql
   ```
   Optionally import demo data:
   ```
   Database/adms_demo.sql
   ```

5. Open the application in your browser:
   - Admin portal:  `http://localhost/ADMS/Admin/`
   - Alumni portal: `http://localhost/ADMS/Alumni/`

---

## Configuration

All database settings live in `config.php` at the project root. This file is listed in `.gitignore` and is **never committed to GitHub**.

Use `config.example.php` as the starting template:

```php
$servername = "localhost";
$username   = "demo_user";   // replace with your XAMPP username (usually "root")
$password   = "demo_pass";   // replace with your XAMPP password (usually "")
$dbname     = "adms_demo";   // replace with your local database name
```

---

## Default Demo Credentials

| Role   | Username / ID    | Password   |
|--------|------------------|------------|
| Admin  | `admin`          | `123456` |
| Alumni | *(see demo SQL)* | *(see demo SQL)* |

> Change default credentials before any public or production deployment.

---

## Session Architecture

- **Admin portal** uses the default PHP session (`PHPSESSID` / `$_SESSION['admin_id']`).
- **Alumni portal** uses a custom session initializer (`adms_session_start_community()`) with an `ADMS_ALUMNI` cookie.

Mixing the two session patterns causes silent authentication failures — always verify which pattern applies when working across portals.

---

## Future Work

- Integration with the **My QU** student/alumni portal.
- **Public deployment** with security hardening (HTTPS, rate limiting, input sanitization audit).
- **Alumni Events Module** with RSVP functionality.

---

## License

This project is an academic demonstration. All rights reserved by the authors and Qassim University. Not licensed for commercial use.

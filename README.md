# Websys Project

## Local Hosting & Device Connection

To access this website from other devices (e.g., your mobile phone) on your local network:

1. **Find your computer's local IP address:**
   - Open Command Prompt and run: `ipconfig`
   - Look for the IPv4 Address (e.g., `192.168.1.10`).

2. **Connect your device to the same Wi-Fi/network.**

3. **Open your browser on the device and enter:**
   - `http://<your-ip-address>/Websys`
   - Example: `http://192.168.1.10/Websys`

4. **Make sure XAMPP (Apache) is running on your computer.**

---

## Website Setup Instructions

1. **Install XAMPP:**
   - Download and install XAMPP from [apachefriends.org](https://www.apachefriends.org/).

2. **Start Apache and MySQL:**
   - Open XAMPP Control Panel and click 'Start' for Apache and MySQL.

3. **Place the project folder:**
   - Copy the `Websys` folder to `C:/xampp/htdocs/`.

4. **Import the database:**
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Create a new database (e.g., `websys_db`).
   - Import `schema.sql` from the project folder.

5. **Configure database connection:**
   - Edit `src/config.php` and update database credentials if needed.

6. **Access the website:**
   - Open your browser and go to `http://localhost/Websys`.

---

For network access, use your local IP address as described above.

---

## Troubleshooting
- Ensure Apache and MySQL are running.
- Make sure firewall allows incoming connections on port 80 (for Apache).
- Devices must be on the same local network.

---

For further help, contact your project administrator.

# Student Organization Management and Budget Transparency System

Simple PHP + MySQL (XAMPP) + Tailwind CSS application with roles:
- admin
- student
- owner (organization creator)

## Features implemented
- Authentication (register/login/logout)
- Admin organization CRUD
- Admin can assign one student as organization owner
- Owner can update organization information
- Owner can create/delete announcements
- Owner can create/update/delete income and expense entries
- Optional receipt upload (jpg/jpeg/png/pdf)
- Students can join organizations as members
- All students can view organization announcements and budget reports
- Admin can filter student information by name or email

## Stack
- PHP 8+
- MySQL (PDO)
- Tailwind CSS (CDN)

## Project setup (Windows + XAMPP)

### 1) Prerequisites
- XAMPP (Apache + MySQL)
- PHP 8+ (included in XAMPP)
- MySQL enabled in XAMPP
- Optional: Git and GitHub CLI (`gh`) for publishing

### 2) Place the project in XAMPP htdocs
1. Copy this project folder to:
   `C:/xampp/htdocs/websys`
2. Final file path should look like:
   `C:/xampp/htdocs/websys/index.php`

### 3) Start services
1. Open XAMPP Control Panel.
2. Start:
   - Apache
   - MySQL

### 4) Open the app
Go to:
- `http://localhost/websys/index.php`

The app auto-creates database `websys_db` and tables on first run.

### 5) Default admin login
- Email: admin@campus.local
- Password: admin123

### 6) Configure database (if needed)
- DB config is in `src/config.php`.
- Default setup expects local MySQL (`127.0.0.1`) and user `root`.
- If your MySQL password is not empty, update it in `src/config.php`.

## Google Login Integration
1. Go to Google Cloud Console and create/select a project.
2. Configure OAuth consent screen.
3. Create OAuth Client ID (Web application).
4. Add Authorized redirect URIs:
   - `http://localhost/websys/index.php?page=google_callback` (XAMPP)
   - `http://localhost:8000/index.php?page=google_callback` (built-in PHP server)
5. Open [src/config.php](src/config.php) and set:
   - `google_oauth.client_id`
   - `google_oauth.client_secret`
6. Reload login page and click **Continue with Google**.

## Run with PHP built-in server (optional)
1. Open terminal at project root.
2. Start PHP server:
   php -S localhost:8000
3. Open browser:
   http://localhost:8000/index.php

## Publish to GitHub (new repository)

### Option A: GitHub website + terminal (easy)
1. Create a new empty repository in your GitHub account (do not initialize with README).
2. In terminal, open project root (`C:/Users/Mark/Desktop/Websys`) and run:
   - `git init`
   - `git add .`
   - `git commit -m "Initial commit: Student Org Management System"`
   - `git branch -M main`
   - `git remote add origin https://github.com/<your-username>/<your-repo>.git`
   - `git push -u origin main`

### Option B: GitHub CLI (`gh`) create + push
1. Login once:
   - `gh auth login`
2. In project root, run:
   - `git init`
   - `git add .`
   - `git commit -m "Initial commit: Student Org Management System"`
   - `gh repo create <your-repo-name> --public --source . --remote origin --push`

Tip: Use `--private` instead of `--public` if you want a private repo.

## Updates (2026-02-25)
- Added organization visibility categories: collegewide, institute-wide, and program-based.
- Added student profile academic fields (`institute`, `program`) and registration program capture.
- Enforced backend join restrictions so students can only join eligible organizations.
- Added dashboard “View All” modal popups for organizations and latest announcements.
- Added announcement controls: latest-first ordering, 30-day expiration purge, and admin pin/unpin for one Important announcement.
- Improved light-mode readability and modal styling.
- Expanded deterministic dummy seed data for broader testing coverage.

## Previous System Updates
- Added admin audit logs page and dashboard access for activity monitoring.
- Fixed admin audit route/runtime issues (DB-compatible date filtering, auth check, and DB handle usage).
- Refined dashboard pagination behavior per section to reduce clutter and improve navigation.
- Updated activity feed to show 4 items per page and added section-specific “View All” flows.
- Updated recent income/expense reports to show the latest 8 entries without pagination.
- Removed outdated “Demo” wording from seeded/testing data.
- Improved organization and financial summary visibility to reflect user-relevant organizations.
- Fixed modal rendering issue that previously exposed broken PHP markup in the UI.
- Removed “(CRUD)” wording from Transaction History labels.
- Added fixed-date transaction seeding and improved sample date variety for realistic testing.
- Added richer dummy dataset coverage across users, organizations, announcements, memberships, and transactions.
- Converted “View All” navigation from separate pages into in-dashboard modal popups.
- Removed “carousel” wording from announcements display and aligned section naming with dashboard UX.
- Applied latest-to-oldest ordering for latest announcements.
- Added 30-day automatic expiration cleanup for announcements.
- Added admin ability to pin one Important announcement at a time, plus unpin control.
- Improved light-mode contrast and readability for key dashboard text and cards.
- Improved modal visual style (including light-mode glass treatment) and themed scrollbars.
- Made organizations and financial summaries scrollable where needed to reduce dashboard clutter.
- Added organization sorting/labeling by visibility category for clearer student browsing.
- Filtered student-visible organizations based on profile eligibility (collegewide/institute/program rules).
- Added server-side enforcement to block organization joins when institute/program does not match requirements.

## Notes
- Uploaded receipts are stored in `public/uploads/`.
- Schema reference is in `schema.sql`.

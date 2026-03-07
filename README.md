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

## Run with XAMPP (recommended)
1. Copy this project to:
   C:/xampp/htdocs/websys
2. Open XAMPP Control Panel.
3. Start Apache and MySQL.
4. Open browser:
   http://localhost/websys/index.php

The app auto-creates database `websys_db` and tables on first run.

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

## Default admin account
- Email: admin@campus.local
- Password: admin123

## Notes
- DB config is in `src/config.php`.
- Uploaded receipts are stored in `public/uploads/`.
- Schema reference is in `schema.sql`.

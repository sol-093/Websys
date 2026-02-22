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

## Notes
- Uploaded receipts are stored in `public/uploads/`.
- Schema reference is in `schema.sql`.

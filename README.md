# Teacher Leave & On-Duty (OD) Management System

A web-based portal designed for colleges to automate faculty leave applications, On-Duty (OD) verifications with QR codes, timetable class alteration suggestions, and multi-level approvals (HOD & Principal).

---

## 🌟 Key Features

- **Multi-Role Access**: Separate dashboards for **Staff (Faculty)**, **HOD**, and **Admin / Principal**.
- **Leave & OD Application**: Casual Leave (CL) quota tracking and On-Duty (OD) requests with reason and document attachments.
- **Timetable Class Alteration**: Automatic substitute faculty suggestions based on weekly period schedules.
- **Hierarchical Approvals**: Multi-tier approval flow (Faculty &rarr; HOD &rarr; Principal).
- **Progressive Web App (PWA)**: Installable as a standalone app on both **Desktop (Windows/Mac)** and **Mobile (Android/iOS)**.
- **Dual Authentication**: Secure password login with bcrypt hashing + Google Sign-In support.
- **Pure White Professional UI**: Clean academic theme built with Bootstrap 5 and Lucide Icons.

---

## 📁 Project Structure

```text
├── admin/                     # Admin & HOD dashboard, approvals, reports, analytics
├── staff/                     # Teacher dashboard, apply leave, history, notifications
├── includes/                  # Database connection (db_config.php) & helper functions
├── assets/
│   ├── css/style.css          # Pure White Academic styling & mobile media queries
│   ├── js/                    # Client-side scripts
│   └── images/                # College logos and profile pictures
├── database/
│   └── teacher_leave_od.sql   # Complete database schema and initial data
├── index.php                  # Login & Role selector page
├── google_auth.php            # Google Sign-in integration
├── manifest.json              # PWA manifest for desktop/mobile app installation
├── sw.js                      # PWA service worker
└── run.bat                    # One-click Windows local server launcher
```

---

## 🚀 Setup & Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+)

### 1. Database Setup
1. Open XAMPP and start **Apache** and **MySQL**.
2. Go to `http://localhost/phpmyadmin`.
3. Create a new database named `teacher_leave_od`.
4. Click **Import** and upload `database/teacher_leave_od.sql`.

### 2. Configure Database Connection
Open `includes/db_config.php` and verify credentials:
```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'teacher_leave_od');
```

### 3. Run the Application
You can run it in two ways:

#### Option A: Quick Launcher (Windows)
Double-click `run.bat` in the project folder.

#### Option B: Terminal
Open a terminal in the project directory and run:
```bash
php -S 0.0.0.0:8888
```

- **On Desktop**: Open `http://localhost:8888/`
- **On Mobile (Same Wi-Fi)**: Open `http://<your-pc-ip>:8888/`

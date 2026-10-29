# CHARUSAT StudentHub Portal
**Comprehensive Full-Stack Academic Portal & WDF Practicals Suite (Practicals 7–14)**
*B.Tech Computer Engineering | Web Development Frameworks (WDF)*

---

## 1. Project Overview

The **CHARUSAT StudentHub Portal** is a production-grade, secure, responsive college web application built for the Web Development Frameworks (WDF) curriculum (Practicals 7 through 14). It combines a student portal, public-facing academic information pages, a dynamic student dashboard with live event registration, a database-backed profile management system, an administrative control panel with role-based access control (RBAC), and an automated MySQL integration test suite.

### Key Architecture Highlights
- **Frontend:** Semantic HTML5, modern CSS3 design system (responsive CSS Grid/Flexbox, custom properties, light/dark themes, accessible typography), and modular vanilla JavaScript (ES6+).
- **Backend:** Native PHP 8.2 with PDO (PHP Data Objects), prepared statements, robust CSRF protection, secure session management, and Bcrypt password hashing.
- **Database:** Relational MySQL/MariaDB schema with foreign key constraints, atomic transactions, indexes, and soft-delete capabilities.
- **Testing:** Standalone, automated MySQL integration testing suite with zero external dependencies, providing 100% assertion coverage across all core workflows.

---

## 2. Requirements

- **PHP:** Version 8.0 or newer (tested with PHP 8.2.12).
  - Required PHP Extensions: `pdo_mysql`, `mysqli`, `mbstring`, `fileinfo`, `openssl`, `json` (all standard in XAMPP).
- **Database:** MySQL 5.7+ or MariaDB 10.4+ (included with XAMPP).
- **Web Server:** Apache (via XAMPP) or PHP Built-in Web Server.

---

## 3. Quick Start & Local Execution

### Option A — XAMPP (Apache + MySQL) [Standard College Setup]
1. Copy or extract the `Student_Portal` folder to:
   ```text
   C:\xampp\htdocs\Student_Portal
   ```
2. Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
3. Open your browser and navigate to:
   ```text
   http://localhost/Student_Portal/
   ```

### Option B — PHP Built-in Server (One-Click / Standalone)
1. Ensure MySQL is running (via XAMPP or local service).
2. **Windows:** Double-click `START_SERVER.bat` in the project root.
   - Alternatively, open PowerShell/Command Prompt in `Student_Portal` and run:
     ```cmd
     php -S 127.0.0.1:8000 router.php
     ```
3. Open your browser and navigate to:
   ```text
   http://127.0.0.1:8000/
   ```

> **IMPORTANT: Why opening files directly (`file://` or VS Code Live Server) will not work:**
> PHP files (`.php`) require server-side execution by a PHP runtime engine. Opening `.php` files via double-click or VS Code "Live Server" will display raw PHP source code. Always access the application through Apache (`http://localhost/Student_Portal/`) or the PHP built-in server (`http://127.0.0.1:8000/`). A client-side detection banner (`js/php_check.js`) will alert you if an unsupported protocol is detected.

---

## 4. Database Setup & Configuration

### Default Database Settings
The application defaults to standard local development settings:
- **Host:** `127.0.0.1` (or `localhost`)
- **Port:** `3306`
- **Database:** `studenthub`
- **Username:** `root`
- **Password:** ` ` *(empty string)*

To customize settings without editing core files, copy `config.example.php` to `config.php` (or copy `.env.example` to `.env`) and adjust your credentials. Both `db.php` and `tests/test_config.php` automatically load overrides from `config.php`.

### Database Initialization Methods (Choose One)

#### Method 1: phpMyAdmin or MySQL CLI (Recommended)
1. Open phpMyAdmin (`http://localhost/phpmyadmin/`) or open your MySQL terminal.
2. Import the provided SQL script:
   ```sql
   source sql/studenthub_complete.sql;
   ```
   *This creates the `studenthub` database, all required tables, foreign keys, indexes, and pre-seeds verified demo accounts.*

#### Method 2: Web Installer (`setup.php`)
1. Open your browser to:
   ```text
   http://localhost/Student_Portal/setup.php
   ```
   *(or `http://127.0.0.1:8000/setup.php`)*
2. The setup wizard automatically detects missing tables and columns, provisions the schema safely without destroying existing student records, and allows you to promote any user to an administrator.

---

## 5. Pre-Seeded Accounts & Credentials

The database script initializes the following pre-configured, verified accounts:

| Role | Username | Email | Password | Access Level |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@charusat.edu.in` | `Admin@123` | Full administrative control: User management, student CRUD, event management, FAQs, feedback/contact messages, and system audit logs. |
| **Student** | `kush_student` | `25ce030@charusat.edu.in` | `Student@123` | Student portal: View and update personal profile, change password, browse and register for campus events, view active registrations. |

*Note: The login system accepts either the student's **Email** or **Username**.*

---

## 6. Automated MySQL Integration Testing

A complete, automated integration test suite is included in the `tests/` directory to verify database integrity, authentication flows, authorization gates, and CRUD operations against a live MySQL instance.

### Running the Tests

#### On Windows:
Double-click `run_tests.bat` or run from PowerShell/CMD:
```cmd
run_tests.bat
```

#### Via PHP CLI:
```cmd
php tests/run_tests.php
```

### Test Suite Specifications
- **Dedicated Test Database:** The test runner creates and runs exclusively against `studenthub_test`, ensuring real production/development data is never touched or polluted.
- **Automated Isolation & Cleanup:** Each test method executes in an isolated harness that initializes fresh tables and cleans up test fixtures after completion.
- **Verified Coverage (16 Test Methods, 62 Assertions):**
  1. `testDatabaseConnection`: Verifies active PDO connection to MySQL.
  2. `testDatabaseSchemaAndTables`: Confirms presence of all 6 tables (`users`, `students`, `events`, `event_registrations`, `admin_audit_logs`, `remember_tokens`).
  3. `testSuccessfulUserRegistration`: Asserts atomic creation of linked `users` and `students` records.
  4. `testInvalidRegistrationData`: Tests boundary conditions (short password, invalid email format).
  5. `testDuplicateAccountRegistration`: Confirms duplicate username/email prevention via UNIQUE constraints.
  6. `testPasswordHashingAndVerification`: Validates Bcrypt `PASSWORD_BCRYPT` hash verification.
  7. `testLoginWithCorrectCredentials`: Asserts successful credential verification and role assignment.
  8. `testLoginWithIncorrectCredentials`: Asserts rejection of invalid passwords.
  9. `testRetrievingUserAndProfileData`: Tests profile query joining `users` and `students`.
  10. `testUpdatingExistingRecords`: Verifies profile and student record updating.
  11. `testCreatingAndDeletingRecords`: Asserts event creation and relational deletion.
  12. `testEventsOrStudentCrud`: Tests complete student lifecycle (Insert, Query, Update, Soft-delete).
  13. `testAdminAuthorization`: Enforces RBAC permissions check (`sh_require_admin`).
  14. `testUnauthorizedAccessToProtectedPages`: Confirms unauthenticated users are redirected.
  15. `testSessionHandlingAndLogout`: Asserts session invalidation and token revocation on logout.
  16. `testDatabaseErrorsAndInvalidInputHandling`: Verifies foreign key violation handling.

---

## 7. WDF Practicals Mapping (Practicals 7 to 14)

| Practical | Title / Topic | Implemented Files | Description & Key Features |
| :---: | :--- | :--- | :--- |
| **7** | PHP Form Processing & CSV Storage | `practical7.php`, `api/contact.php`, `contact.html`, `feedback.html` | Server-side validation of text, email, and phone input; sanitization via `htmlspecialchars`; append-only CSV persistence with file locking. |
| **8** | Database Connectivity via PDO | `practical8.php`, `db.php`, `sql/studenthub_practical8.sql` | Establishing robust PDO MySQL connections, error handling with `PDOException`, prepared statements, and query execution. |
| **9** | User Registration & Hashing | `practical9.php`, `auth.php` | Atomic registration inserting both `users` and `students` records; Bcrypt password hashing (`PASSWORD_BCRYPT`, cost 12); CSRF token verification; input validation. |
| **10** | Authentication & Session Management | `login.php`, `logout.php`, `dashboard.php`, `profile.php`, `auth.php` | Secure login supporting email or username; 15-minute idle session timeout; session fixation prevention with `session_regenerate_id(true)`; remember-me cookies; secure logout. |
| **11** | Student Management CRUD | `student_management.php` | Full student records management: pagination, real-time search, department filtering, soft-delete (`deleted_at`) and restore capabilities. |
| **12** | Event Management & File Upload | `event_management.php`, `events.html`, `api/events.php` | Campus event scheduling; secure poster image upload with MIME validation via `finfo_file`, 2MB size limit, randomized naming, and direct execution prevention. |
| **13** | REST APIs & Asynchronous Fetch | `api/students.php`, `student_ajax.php`, `js/students_page.js` | RESTful JSON API endpoints supporting GET, POST, PUT, DELETE; live asynchronous directory updates via Fetch API. |
| **14** | Admin Analytics & System Audit | `admin_dashboard.php`, `admin_users.php`, `admin_audit.php` | Database-backed KPI metric cards; monthly registration trend analytics; user account activation/deactivation; comprehensive system audit log trail. |

---

## 8. Security & Engineering Best Practices

1. **SQL Injection Defense:** All database queries handling user-supplied parameters use PDO prepared statements with parameterized tokens (`:param` or `?`).
2. **Cross-Site Scripting (XSS) Prevention:** Dynamic output is sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. On the client side, dynamic content is injected using `textContent` rather than `innerHTML`.
3. **Cross-Site Request Forgery (CSRF) Protection:** State-changing POST requests (`login.php`, `practical9.php`, `profile.php`, `dashboard.php`, admin forms) validate cryptographically secure random session tokens (`sh_csrf_field()` / `sh_verify_csrf()`).
4. **Password Security:** Passwords are hashed using the standard PHP `password_hash()` with `PASSWORD_BCRYPT`. Plaintext passwords are never stored in the database or logged.
5. **Role-Based Access Control (RBAC):** Administrative pages call `sh_require_admin()` to prevent unauthorized URL direct-access attacks. Students attempting to view admin routes are redirected with an alert.
6. **Session Hardening:** Sessions expire automatically after 15 minutes of inactivity (`SH_SESSION_IDLE_TIMEOUT = 900`). Session IDs are regenerated upon login and logout to mitigate session fixation attacks.
7. **Secure File Uploads:** Uploaded poster images are inspected with `finfo_file()` to verify legitimate image MIME types (`image/jpeg`, `image/png`, `image/webp`). Target directories disable script execution via `.htaccess`.

---

## 9. Project Directory Structure

```text
Student_Portal/
│
├── api/                            # RESTful JSON API Endpoints
│   ├── contact.php                 # Contact form submission endpoint
│   ├── events.php                  # Public event listing API
│   ├── feedback.php                # Student feedback submission endpoint
│   └── students.php                # Student directory REST API (CRUD)
│
├── archive_static_prototypes/      # Preserved early static HTML prototypes
│
├── assets/                         # SVG graphics and vector campus illustrations
│
├── css/                            # Modern CSS3 Design System
│   ├── admin.css                   # Admin panel styling, data tables, metrics cards
│   └── style.css                   # Core portal stylesheet, responsive grid, dark theme
│
├── data/                           # Data storage & fallback mock fixtures
│   ├── .htaccess                   # Denies direct HTTP access to CSV/JSON files
│   ├── contact_submissions.csv     # Contact form CSV archive
│   ├── events.json                 # Fallback event data
│   ├── faqs.json                   # FAQ knowledge base data
│   ├── locations.json              # Campus location mapping
│   └── students.json               # Fallback student directory data
│
├── js/                             # Frontend JavaScript (ES6 Modules)
│   ├── modules/                    # Modular helpers (API, filters, pagination, render)
│   ├── auth_forms.js               # Form client validation & password matching
│   ├── auth_nav.js                 # Dynamic navigation bar based on auth session
│   ├── contact_form.js             # Asynchronous contact submission
│   ├── events_page.js              # Live event cards renderer
│   ├── faq_page.js                 # Collapsible FAQ accordion
│   ├── php_check.js                # Protocol and PHP runtime detection notice
│   ├── students_page.js            # Live student directory with search & filters
│   ├── theme.js                    # Light / dark theme toggle with localStorage persistence
│   └── validation.js               # Comprehensive client-side form validation rules
│
├── sql/                            # Database Schemas & ER Documentation
│   ├── ER_MODEL.md                 # Entity-Relationship diagram & schema documentation
│   ├── studenthub_complete.sql     # Master SQL schema with foreign keys and seed accounts
│   └── studenthub_practical8.sql   # Practical 8 isolated schema
│
├── tests/                          # Automated MySQL Integration Test Suite
│   ├── IntegrationTestCase.php     # Base test harness with assertions & database helpers
│   ├── run_tests.php               # CLI test runner with ANSI formatted output
│   └── test_config.php             # Dedicated test database configuration
│
├── uploads/                        # User-uploaded media
│   └── event_posters/              # Stored event posters (.htaccess execution protected)
│
├── about.html                      # About CHARUSAT & portal architecture overview
├── admin_audit.php                 # Admin: System audit log inspection
├── admin_dashboard.php             # Admin: KPI analytics and registration metrics
├── admin_faqs.php                  # Admin: FAQ management
├── admin_messages.php              # Admin: Contact and feedback message viewer
├── admin_users.php                 # Admin: User account management & role promotion
├── auth.php                        # Core authentication and authorization library
├── auth_status.php                 # JSON endpoint returning current session status
├── CHANGES.md                      # Detailed technical changelog of all repairs
├── config.example.php              # Template for custom database settings
├── contact.html                    # Public contact form page
├── dashboard.php                   # Student portal: event registration & dashboard
├── db.php                          # Master database connection manager (PDO & MySQLi)
├── event_management.php            # Admin: Event scheduling and poster upload CRUD
├── events.html                     # Public campus events directory
├── faq.html                        # Campus FAQ & knowledge base
├── feedback.html                   # Campus feedback submission form
├── index.html                      # Public portal homepage
├── layout.php                      # Admin panel shared sidebar & header template
├── login.php                       # Student & Admin secure login page
├── logout.php                      # Session invalidation & logout handler
├── practical7.php                  # Practical 7 standalone demo (Form to CSV)
├── practical8.php                  # Practical 8 standalone demo (PDO CRUD)
├── practical9.php                  # Practical 9 user registration page
├── profile.html                    # Redirection fallback to dynamic profile
├── profile.php                     # Dynamic database-backed student profile & settings
├── README.md                       # Comprehensive project documentation
├── README_Practical8.md            # Practical 8 specific instructions
├── router.php                      # Routing script for PHP built-in web server
├── run_tests.bat                   # Windows batch file to run integration tests
├── setup.php                       # Interactive database installation wizard
├── START_SERVER.bat                # Windows batch file to launch PHP built-in server
├── start_server.sh                 # Linux/macOS shell script to launch PHP built-in server
├── storage.php                     # JSON & CSV file persistence utilities
├── student_ajax.php                # Admin: Asynchronous AJAX student management view
├── student_management.php          # Admin: Standard paginated student management view
└── students.html                   # Public student directory page
```

---

## 10. Troubleshooting & FAQ

| Problem | Root Cause | Solution |
| :--- | :--- | :--- |
| **Page displays raw PHP code** | Opening file directly with browser (`file://`) or VS Code Live Server. | Access through Apache (`http://localhost/Student_Portal/`) or run `START_SERVER.bat`. |
| **"Database connection failed"** | MySQL service not running or port mismatch. | Start MySQL in XAMPP. If your MySQL runs on port `3307`, copy `config.example.php` to `config.php` and set `'port' => 3307`. |
| **"Table or column missing" error** | Database was not initialized. | Import `sql/studenthub_complete.sql` in phpMyAdmin or visit `setup.php` in your browser. |
| **Integration tests fail to connect** | MySQL daemon is not running. | Start MySQL in XAMPP or run `mysqld` service before running `run_tests.bat`. |
| **Poster image upload fails** | Directory permission restriction. | Verify that `uploads/event_posters/` exists and is writable by the web server process. |

---

## 11. Academic Submission Note

This project is prepared and verified for submission for **Web Development Frameworks (WDF)** practical evaluations. All requirements for Practicals 7 through 14 have been implemented, tested, and validated in accordance with college academic standards.

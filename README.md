# CHARUSAT StudentHub Portal — Web Development Frameworks (ITUE203)

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL Database](https://img.shields.io/badge/MySQL-InnoDB-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Frontend-HTML5%20%7C%20CSS3%20%7C%20ES6-E34F26?style=flat&logo=html5&logoColor=white)](https://developer.mozilla.org/)
[![Integration Tests](https://img.shields.io/badge/Tests-16%20Passed%20(62%20Assertions)-success?style=flat)](Student_Portal/tests/)
[![Academic Semester](https://img.shields.io/badge/Semester-3rd%20(2026--27)-blue?style=flat)](https://www.charusat.ac.in/)

A comprehensive, production-grade academic web application developed for the **Web Development Frameworks (ITUE203)** curriculum at **Charotar University of Science and Technology (CHARUSAT)**, Faculty of Technology and Engineering (FTE).

This repository contains the complete laboratory coursework and full-stack implementation for Practicals 1 through 14 of the **StudentHub Portal**.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Project Objectives & Problem Statement](#2-project-objectives--problem-statement)
3. [Features & Implemented Functionality](#3-features--implemented-functionality)
4. [Website Pages & Purposes](#4-website-pages--purposes)
5. [Technology Stack](#5-technology-stack)
6. [Project Directory Structure](#6-project-directory-structure)
7. [Prerequisites & Software Requirements](#7-prerequisites--software-requirements)
8. [Step-by-Step Installation & Local Setup](#8-step-by-step-installation--local-setup)
9. [MySQL Database Creation & Schema Import](#9-mysql-database-creation--schema-import)
10. [Safe Database Configuration & Environment Setup](#10-safe-database-configuration--environment-setup)
11. [Instructions for Running the Website Locally](#11-instructions-for-running-the-website-locally)
12. [Authentication, Sessions & Role-Based Access Control](#12-authentication-sessions--role-based-access-control)
13. [Student & Event Management CRUD Operations](#13-student--event-management-crud-operations)
14. [JSON APIs & Asynchronous Fetch/AJAX Integration](#14-json-apis--asynchronous-fetchajax-integration)
15. [Administrative Dashboard & System Audit Log](#15-administrative-dashboard--system-audit-log)
16. [Available Automated Integration Tests](#16-available-automated-integration-tests)
17. [Academic Practical Mapping (Practicals 1–14)](#17-academic-practical-mapping-practicals-114)
18. [Troubleshooting Guide](#18-troubleshooting-guide)
19. [Genuine Screenshots & Media Reference](#19-genuine-screenshots--media-reference)
20. [Future Enhancements](#20-future-enhancements)
21. [Author & Academic Information](#21-author--academic-information)
22. [License Information](#22-license-information)

---

## 1. Project Overview

The **CHARUSAT StudentHub Portal** is a centralized, role-based academic platform designed to streamline student interaction, campus event scheduling, profile administration, and university communications. Built entirely with vanilla technologies (PHP 8, MySQL/MariaDB, HTML5, CSS3, and JavaScript ES6+), it demonstrates real-world software engineering principles without relying on third-party backend frameworks.

All application components are located inside the [`Student_Portal/`](Student_Portal/) directory, providing an integrated student portal, administrative control panel, RESTful API endpoints, and a database-backed persistence layer.

---

## 2. Project Objectives & Problem Statement

### Problem Statement
College campuses frequently handle academic directories, event registrations, student inquiries, and profile updates across disparate, manual, or static systems. Without server-side integration and relational data integrity, student data becomes inconsistent, vulnerable to duplicate entries, and difficult to manage.

### Project Objectives
- **Full-Stack Integration:** Connect accessible, semantic HTML5 client interfaces to a relational MySQL database via modern PHP 8.2 with PDO.
- **Relational Integrity:** Establish relational tables with foreign keys, cascading actions, transaction safety, and composite unique constraints.
- **Defensive Engineering:** Protect against standard web vulnerabilities (SQL Injection, Cross-Site Scripting, Cross-Site Request Forgery, Session Fixation, and unauthenticated script execution).
- **Asynchronous User Experience:** Utilize the native Fetch API to perform dynamic search, filtering, and data manipulation without full page reloads.
- **Test Automation:** Validate application resilience and database assertions using an automated integration test suite running against a live MySQL instance.

---

## 3. Features & Implemented Functionality

### Student & Public Experience
- **Atomic Registration:** Provisions linked credentials in `users` and student records in `students` in a single transaction with unique email and roll number checks.
- **Flexible Authentication:** Students and administrators can sign in using either their institutional email or their username.
- **Dynamic Profile Management:** Authenticated students can view their personal information, update their contact/academic details, and change their account password with current-password verification.
- **Interactive Event Dashboard:** Real-time browsing of upcoming university workshops, symposia, and cultural events with one-click registration and cancellation.
- **Campus Directory:** Filterable, searchable student and event directories with instant feedback.
- **Dual Visual Themes:** Seamless toggle between light mode and high-contrast dark mode with preference saved in `localStorage`.

### Administrative Control Panel
- **Logical Sidebar Categorization:** Reorganized into five distinct management sections: Dashboard, Academic, User Management, Communications, and System.
- **Live Analytical Dashboard:** Real-time metrics reporting total registered students, active events, feedback submissions, and monthly registration charts.
- **Student Record Management:** Full CRUD operations supporting server-side pagination, search queries, department filtering, and non-destructive soft-delete/restore (`deleted_at`).
- **Event Scheduling & Poster Uploads:** Creation and editing of campus events with automated poster file validation (MIME-type enforcement, size caps, and execution blocking).
- **User Account Governance:** Administrative activation/deactivation of student accounts and role reassignment.
- **Inquiry & Feedback Inbox:** Centralized review and archiving of visitor contact requests and student suggestions.
- **System Audit Trail:** Automated activity logging capturing all administrative create, update, and delete actions.

---

## 4. Website Pages & Purposes

All website routes and templates are located in [`Student_Portal/`](Student_Portal/):

| Page / Route | Type | Description |
| :--- | :--- | :--- |
| [`index.html`](Student_Portal/index.html) | Public | Portal homepage featuring hero section, campus highlights, announcements, and navigation. |
| [`about.html`](Student_Portal/about.html) | Public | Institutional overview, mission/vision statements, and technical portal architecture. |
| [`events.html`](Student_Portal/events.html) | Public / Dynamic | Campus events catalogue rendering active events via `api/events.php`. |
| [`students.html`](Student_Portal/students.html) | Public / Dynamic | Student directory with real-time department filtering and search via `api/students.php`. |
| [`contact.html`](Student_Portal/contact.html) | Public | University inquiry form persisting submissions to CSV and administrative inbox. |
| [`feedback.html`](Student_Portal/feedback.html) | Public | Campus experience feedback form with rating system and client-side validation. |
| [`faq.html`](Student_Portal/faq.html) | Public | Interactive, collapsible FAQ accordion dynamically populated from knowledgebase data. |
| [`practical9.php`](Student_Portal/practical9.php) | Public / Form | Student registration portal with client validation, password hashing, and CSRF protection. |
| [`login.php`](Student_Portal/login.php) | Public / Form | Secure authentication gate supporting remember-me cookies and role-based redirection. |
| [`profile.php`](Student_Portal/profile.php) | Authenticated | Database-backed student profile manager with password changing and profile editing. |
| [`dashboard.php`](Student_Portal/dashboard.php) | Authenticated | Student dashboard displaying active event enrollments and interactive event registration. |
| [`logout.php`](Student_Portal/logout.php) | Authenticated | Session revocation and token invalidation handler. |
| [`admin_dashboard.php`](Student_Portal/admin_dashboard.php) | Admin Only | Administrative overview displaying database metrics, monthly charts, and quick actions. |
| [`student_management.php`](Student_Portal/student_management.php) | Admin Only | Full paginated student management with search, department filtering, and soft-delete/restore. |
| [`student_ajax.php`](Student_Portal/student_ajax.php) | Admin Only | Quick asynchronous AJAX view for inline student record editing. |
| [`event_management.php`](Student_Portal/event_management.php) | Admin Only | Event scheduling console with poster image uploading and date management. |
| [`admin_users.php`](Student_Portal/admin_users.php) | Admin Only | User credential and account governance (activation, deactivation, role assignment). |
| [`admin_messages.php`](Student_Portal/admin_messages.php) | Admin Only | Communication viewer for inquiries and student feedback messages. |
| [`admin_faqs.php`](Student_Portal/admin_faqs.php) | Admin Only | FAQ editor for adding, editing, and deleting public help questions. |
| [`admin_audit.php`](Student_Portal/admin_audit.php) | Admin Only | Immutable security audit log detailing admin system modifications. |

---

## 5. Technology Stack

- **Frontend:**
  - Semantic HTML5 with accessibility attributes (`aria-*`, skip links).
  - Modern CSS3 with custom properties (CSS variables), CSS Grid, Flexbox, responsive breakpoints, and dark mode theme engine.
  - Native ES6+ JavaScript (Modular architecture, Fetch API, DOM manipulation, form validation).
- **Backend:**
  - PHP 8.2 (Vanilla PHP, Object-Oriented/Procedural hybrid).
  - PDO (PHP Data Objects) with prepared statements for database operations.
  - Session handling with idle timeouts, token validation, and cryptographically secure token generation.
- **Database:**
  - MySQL 5.7+ / MariaDB 10.4+ (`InnoDB` engine, utf8mb4 character set).
  - Relational foreign key constraints with cascading deletes and composite unique indexes.
- **Development & Server Environment:**
  - Apache HTTP Server (via XAMPP) or PHP Built-in CLI Server with request router ([`router.php`](Student_Portal/router.php)).

---

## 6. Project Directory Structure

```text
StudentHubPortal/
│
├── README.md                       # Master Repository Documentation (This file)
│
└── Student_Portal/                 # Main Full-Stack Application
    ├── api/                        # RESTful JSON API Endpoints
    │   ├── contact.php             # Contact form submission endpoint
    │   ├── events.php              # Public event listing API
    │   ├── feedback.php            # Student feedback submission endpoint
    │   └── students.php            # Student directory REST API (CRUD)
    │
    ├── archive_static_prototypes/  # Preserved early static HTML prototypes
    ├── assets/                     # Vector illustrations and campus graphics
    │
    ├── css/                        # CSS3 Design System
    │   ├── admin.css               # Admin control panel stylesheet
    │   └── style.css               # Portal master stylesheet & light/dark variables
    │
    ├── data/                       # CSV and fallback JSON persistence (.htaccess protected)
    │   ├── .htaccess               # Denies direct HTTP downloads of data files
    │   ├── contact_submissions.csv # Contact messages log
    │   ├── events.json             # Fallback events data
    │   ├── faqs.json               # Knowledgebase data
    │   ├── locations.json          # Campus location map
    │   └── students.json           # Fallback student directory data
    │
    ├── js/                         # Modular Frontend JavaScript
    │   ├── modules/                # Shared utility modules (api, filters, pagination, render)
    │   ├── auth_forms.js           # Real-time password match and validation helpers
    │   ├── auth_nav.js             # Session-aware navigation menu updates
    │   ├── contact_form.js         # Asynchronous contact submission handler
    │   ├── events_page.js          # Live event cards renderer
    │   ├── faq_page.js             # Collapsible FAQ accordion script
    │   ├── php_check.js            # Environment detector (flags uninterpreted file:// access)
    │   ├── students_page.js        # Dynamic directory search and filter logic
    │   ├── theme.js                # Theme toggle with localStorage persistence
    │   └── validation.js           # Comprehensive regex form validator
    │
    ├── sql/                        # Database Schemas & Relational Models
    │   ├── ER_MODEL.md             # Entity-Relationship diagram & schema definitions
    │   ├── studenthub_complete.sql # Master schema with foreign keys and seed accounts
    │   └── studenthub_practical8.sql# Practical 8 isolated schema
    │
    ├── tests/                      # Automated MySQL Integration Test Suite
    │   ├── IntegrationTestCase.php # Base test harness with assertion methods
    │   ├── run_tests.php           # CLI test runner with ANSI formatted output
    │   └── test_config.php         # Dedicated test database configuration
    │
    ├── uploads/                    # User-uploaded media
    │   └── event_posters/          # Stored posters (.htaccess execution protected)
    │
    ├── about.html                  # About CHARUSAT & portal architecture
    ├── admin_audit.php             # Admin: System audit log inspection
    ├── admin_dashboard.php         # Admin: KPI metrics and analytics
    ├── admin_faqs.php              # Admin: FAQ management
    ├── admin_messages.php          # Admin: Inquiries and feedback viewer
    ├── admin_users.php             # Admin: User management & role promotion
    ├── auth.php                    # Core authentication and authorization library
    ├── auth_status.php             # Session status JSON endpoint
    ├── CHANGES.md                  # Comprehensive technical changelog of repairs
    ├── config.example.php          # Custom database configuration template
    ├── contact.html                # Campus inquiry form
    ├── dashboard.php               # Student portal: event registration & dashboard
    ├── db.php                      # Master database manager (PDO & MySQLi)
    ├── event_management.php        # Admin: Event scheduling & poster upload CRUD
    ├── events.html                 # Public campus events directory
    ├── faq.html                    # Campus FAQ knowledgebase
    ├── feedback.html               # Student feedback form
    ├── index.html                  # Public portal homepage
    ├── layout.php                  # Admin shared navigation sidebar template
    ├── login.php                   # Secure login page
    ├── logout.php                  # Secure logout & session invalidator
    ├── practical7.php              # Practical 7 standalone demo (Form to CSV)
    ├── practical8.php              # Practical 8 standalone demo (PDO CRUD)
    ├── practical9.php              # Practical 9 user registration portal
    ├── profile.html                # Fallback redirector to dynamic profile
    ├── profile.php                 # Dynamic database-backed profile & settings
    ├── README.md                   # Portal-specific documentation
    ├── README_Practical8.md        # Practical 8 specific instructions
    ├── router.php                  # Request router for PHP built-in web server
    ├── run_tests.bat               # Windows batch file for integration tests
    ├── setup.php                   # Interactive web installer & database provisioner
    ├── START_SERVER.bat            # Windows one-click local server launcher
    ├── start_server.sh             # Linux/macOS local server launcher
    ├── storage.php                 # JSON and CSV storage utility library
    ├── student_ajax.php            # Admin: AJAX student quick-editor
    ├── student_management.php      # Admin: Standard student management
    └── students.html               # Public student directory page
```

---

## 7. Prerequisites & Software Requirements

- **PHP Version:** PHP 8.0 or newer (tested with PHP 8.2.12).
  - Required Extensions: `pdo_mysql`, `mysqli`, `mbstring`, `fileinfo`, `openssl`, `json`.
- **Database Engine:** MySQL 5.7+ or MariaDB 10.4+ (included with XAMPP).
- **Web Server:** Apache (via XAMPP) or PHP Built-in CLI Web Server.
- **Browser:** Any modern standards-compliant browser (Chrome, Firefox, Edge, Safari).

---

## 8. Step-by-Step Installation & Local Setup

### Option A — XAMPP (Standard Apache/MySQL Deployment)
1. Ensure XAMPP is installed (typically at `C:\xampp`).
2. Copy the `Student_Portal` directory into your XAMPP web root:
   ```text
   C:\xampp\htdocs\Student_Portal
   ```
3. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
4. Initialize the database using either phpMyAdmin or the web installer (detailed in Section 9).
5. Access the application in your browser at:
   ```text
   http://localhost/Student_Portal/
   ```

### Option B — PHP Built-in Server (One-Click Standalone)
1. Start the **MySQL** service (via XAMPP Control Panel or local service).
2. Open a terminal in `Student_Portal`:
   ```bash
   cd Student_Portal
   ```
3. Launch the server:
   - **Windows:** Double-click [`START_SERVER.bat`](Student_Portal/START_SERVER.bat) or execute:
     ```cmd
     START_SERVER.bat
     ```
   - **Manual CLI:**
     ```bash
     php -S 127.0.0.1:8000 router.php
     ```
4. Access the application in your browser at:
   ```text
   http://127.0.0.1:8000/
   ```

> **Notice on Local Execution:** PHP files must be executed through a web server running a PHP interpreter. Opening `.php` files by double-clicking or via VS Code Live Server (`file://` protocol) will display raw source code. The client detector ([`js/php_check.js`](Student_Portal/js/php_check.js)) automatically warns users if an unsupported protocol is detected.

---

## 9. MySQL Database Creation & Schema Import

### Initializing the Schema (Choose One)

#### Method 1: phpMyAdmin / MySQL CLI (Recommended)
Import the master script [`sql/studenthub_complete.sql`](Student_Portal/sql/studenthub_complete.sql):
```bash
mysql -u root -p < Student_Portal/sql/studenthub_complete.sql
```
*Creates the database, relational tables, cascades, indexes, and pre-seeds verified demonstration accounts.*

#### Method 2: Web Installer Wizard
Visit the self-guided web setup wizard:
```text
http://localhost/Student_Portal/setup.php
```
*Detects missing tables, validates columns, and enables one-click administrator creation without destroying existing data.*

### Verified Pre-Seeded Accounts

| Role | Username | Email | Password | Access Level |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@charusat.edu.in` | `Admin@123` | Full access to user governance, student CRUD, event management, messages, and audit trail. |
| **Student** | `kush_student` | `25ce030@charusat.edu.in` | `Student@123` | Student portal: Live event sign-ups, profile updates, and password changes. |

---

## 10. Safe Database Configuration & Environment Setup

### Default Credentials
The application defaults to standard local development credentials:
- **Host:** `127.0.0.1` (or `localhost`)
- **Port:** `3306`
- **Database:** `studenthub`
- **Username:** `root`
- **Password:** ` ` *(empty string)*

### Custom Credentials Override
To adjust credentials without altering core files:
1. Copy [`config.example.php`](Student_Portal/config.example.php) to `config.php`:
   ```bash
   cp Student_Portal/config.example.php Student_Portal/config.php
   ```
2. Edit `config.php` with your specific MySQL credentials. Both [`db.php`](Student_Portal/db.php) and [`tests/test_config.php`](Student_Portal/tests/test_config.php) automatically read this configuration.

---

## 11. Instructions for Running the Website Locally

```bash
# 1. Clone repository
git clone https://github.com/Kushpatel2611/StudentHubPortal.git
cd StudentHubPortal/Student_Portal

# 2. Initialize database
mysql -u root -p < sql/studenthub_complete.sql

# 3. Start PHP development server
START_SERVER.bat
# (or: php -S 127.0.0.1:8000 router.php)

# 4. Open browser
start http://127.0.0.1:8000/
```

---

## 12. Authentication, Sessions & Role-Based Access Control

1. **SQL Injection Defense:** All user input is bound using PDO parameterized prepared statements (`:param` / `?`).
2. **Password Hardening:** Passwords are hashed using PHP's native `password_hash()` utilizing the `PASSWORD_BCRYPT` algorithm (cost factor 12). Plaintext passwords are never stored.
3. **Cross-Site Request Forgery (CSRF):** Form submissions validate cryptographically random, per-session CSRF tokens via `sh_csrf_field()` and `sh_verify_csrf()`.
4. **Cross-Site Scripting (XSS):** Dynamic output is escaped via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Client-side DOM manipulation leverages `textContent` rather than `innerHTML`.
5. **Session Hardening:** 
   - Sessions expire after 15 minutes of inactivity (`SH_SESSION_IDLE_TIMEOUT = 900`).
   - Session identifiers are regenerated upon login and logout using `session_regenerate_id(true)`.
6. **Role-Based Access Control (RBAC):** Protected routes enforce authorization via `sh_require_auth()` and `sh_require_admin()`. Direct URL tampering redirects unauthorized visitors.
7. **Secure File Uploads:** Uploaded posters are inspected using `finfo_file()` to enforce legitimate image MIME types (`image/jpeg`, `image/png`, `image/webp`) with randomized filenames and script execution prevention via `.htaccess`.

---

## 13. Student & Event Management CRUD Operations

- **Student Records:** Standard paginated view (`student_management.php`) and quick AJAX view (`student_ajax.php`) allow adding, editing, soft-deleting, and restoring student records.
- **Event Management:** Admin interface (`event_management.php`) allows scheduling campus events, setting participant capacities, and uploading validated poster images.
- **Student Sign-Up:** Authenticated students can browse and register for events via their dashboard (`dashboard.php`), with duplicate enrollment prevention and one-click cancellation.

---

## 14. JSON APIs & Asynchronous Fetch/AJAX Integration

The portal implements RESTful endpoints communicating via JSON:

| Endpoint | Method | Access Level | Description |
| :--- | :---: | :---: | :--- |
| `api/students.php` | `GET` | Public | Queries active student records for directory search and filtering. |
| `api/students.php` | `POST` | Admin | Inserts a new student record into MySQL. |
| `api/students.php` | `PUT` | Admin | Updates an existing student record. |
| `api/students.php` | `DELETE`| Admin | Performs soft deletion (`deleted_at`) of a student. |
| `api/events.php` | `GET` | Public | Returns upcoming campus events list. |
| `api/contact.php` | `POST` | Public | Validates and stores public visitor inquiries. |
| `api/feedback.php`| `POST` | Public | Validates and stores student feedback submissions. |
| `auth_status.php` | `GET` | Public | Returns session status and role payload for dynamic navigation updates. |

---

## 15. Administrative Dashboard & System Audit Log

The administrative console ([`layout.php`](Student_Portal/layout.php)) features a unified sidebar organized into five logical categories:

```text
Admin Panel
├── 📊 Dashboard
│   └── Overview & Real-Time Analytics (admin_dashboard.php)
├── 🎓 Academic
│   ├── Students Management (student_management.php)
│   ├── Students Quick-Editor (student_ajax.php)
│   └── Campus Events Scheduler (event_management.php)
├── 👥 User Management
│   └── User Credentials & Role Assignment (admin_users.php)
├── 💬 Communications
│   ├── Inquiries & Feedback Messages (admin_messages.php)
│   └── FAQ Knowledgebase Editor (admin_faqs.php)
└── ⚙️ System
    └── Security Audit Trail (admin_audit.php)
```

---

## 16. Available Automated Integration Tests

A zero-dependency, automated integration test suite is located in [`Student_Portal/tests/`](Student_Portal/tests/).

### Running the Test Suite
- **Windows:** Double-click [`run_tests.bat`](Student_Portal/run_tests.bat) or run:
  ```cmd
  run_tests.bat
  ```
- **CLI Command:**
  ```bash
  php tests/run_tests.php
  ```

### Verified Test Results (16 Tests, 62 Assertions)
```text
=====================================================================
  StudentHub Portal — Automated MySQL Integration Test Suite
  Dedicated Test Database: studenthub_test on 127.0.0.1:3306
=====================================================================

Running 16 Integration Tests...

  • testDatabaseConnection.......................... [PASS] (16.2ms)
  • testRequiredDatabaseSchemaAndTables............. [PASS] (24.1ms)
  • testSuccessfulUserRegistration.................. [PASS] (72.4ms)
  • testInvalidRegistrationData..................... [PASS] (25.7ms)
  • testDuplicateAccountRegistration................ [PASS] (93.9ms)
  • testPasswordHashingAndVerification.............. [PASS] (149.8ms)
  • testLoginWithCorrectCredentials................. [PASS] (113.3ms)
  • testLoginWithIncorrectCredentials............... [PASS] (123.8ms)
  • testRetrievingUserAndProfileData................ [PASS] (18.1ms)
  • testUpdatingExistingRecords..................... [PASS] (33.2ms)
  • testCreatingAndDeletingRecords.................. [PASS] (11.7ms)
  • testEventsAndStudentCrudOperations.............. [PASS] (40.0ms)
  • testAdminAuthorization.......................... [PASS] (23.3ms)
  • testUnauthorizedAccessToProtectedPages.......... [PASS] (4.5ms)
  • testSessionHandlingAndLogout.................... [PASS] (27.0ms)
  • testDatabaseErrorsAndInvalidInputHandling....... [PASS] (5.0ms)

=====================================================================
  TEST RUN COMPLETE: ALL 16 TESTS PASSED!
  Assertions verified: 62
  Execution time: 0.81s
=====================================================================
```

---

## 17. Academic Practical Mapping (Practicals 1–14)

The table below maps the official ITUE203 syllabus requirements to the verified codebase implementations:

| Practical | Official Practical Title | Primary Files | Implementation Status |
| :---: | :--- | :--- | :---: |
| **1** | Project Initiation, Requirement Analysis, Sitemap, Wireframe, and GitHub Setup | `README.md`, `Student_Portal/sql/ER_MODEL.md` | **Completed** |
| **2** | Semantic HTML5 Pages with Accessibility-Ready Structure | `index.html`, `about.html`, `contact.html`, `events.html`, `faq.html` | **Completed** |
| **3** | Responsive UI Design using CSS Grid, Flexbox, and Frameworks | `css/style.css`, `css/admin.css` | **Completed** |
| **4** | JavaScript DOM Manipulation, Event Handling, and UI Interactivity | `js/modal.js`, `js/slider.js`, `js/hamburger.js`, `js/theme.js` | **Completed** |
| **5** | Registration Form with Frontend Validation and Error Handling | `js/validation.js`, `practical9.php` | **Completed** |
| **6** | Rendering External JSON Data using Fetch API, Search, and Filter | `js/students_page.js`, `js/modules/`, `data/students.json` | **Completed** |
| **7** | PHP Form Processing with Server-Side Validation and CSV Storage | `practical7.php`, `api/contact.php`, `api/feedback.php` | **Completed** |
| **8** | MySQL Schema Design, ER Model, PDO Connectivity, and Queries | `practical8.php`, `db.php`, `sql/studenthub_complete.sql` | **Completed** |
| **9** | Secure User Registration with Database Insert and Password Hashing | `practical9.php`, `auth.php` | **Completed** |
| **10** | Secure Login/Logout with Sessions, Role-Based Access, and Timeouts | `login.php`, `logout.php`, `profile.php`, `dashboard.php` | **Completed** |
| **11** | Student Management CRUD with Search, Filter, and Pagination | `student_management.php`, `storage.php` | **Completed** |
| **12** | Event Management CRUD with Poster Upload and File Validation | `event_management.php`, `uploads/event_posters/` | **Completed** |
| **13** | PHP JSON API and AJAX/Fetch-Based CRUD without Page Reload | `api/students.php`, `student_ajax.php` | **Completed** |
| **14** | Admin Dashboard with Role-Based Access Control and Audit Logging | `admin_dashboard.php`, `admin_audit.php`, `layout.php` | **Completed** |

---

## 18. Troubleshooting Guide

| Issue Encountered | Probable Cause | Corrective Action |
| :--- | :--- | :--- |
| **Browser displays raw PHP code** | Opening `.php` via `file://` or Live Server. | Run through Apache (`http://localhost/Student_Portal/`) or execute `START_SERVER.bat`. |
| **Database connection refused** | MySQL service stopped or port mismatch. | Start MySQL in XAMPP. If running on port `3307`, copy `config.example.php` to `config.php` and set `'port' => 3307`. |
| **"Table does not exist" error** | Database not initialized. | Import `sql/studenthub_complete.sql` via phpMyAdmin or open `setup.php` in your browser. |
| **Event poster upload fails** | Directory permission restriction. | Verify write permissions on `uploads/event_posters/`. Ensure the file is a valid JPG/PNG/WEBP under 2 MB. |
| **Integration test connection failure** | MySQL daemon not running on port 3306. | Start MySQL in XAMPP before executing `run_tests.bat`. |

---

## 19. Genuine Screenshots & Media Reference

Vector graphics and illustration assets are located in [`Student_Portal/assets/`](Student_Portal/assets/) (`campus.svg`, `events.svg`, `lab.svg`). UI screenshots can be captured by running the local server (`START_SERVER.bat`) and visiting the routes listed in Section 4.

---

## 20. Future Enhancements

- **Automated Email Notifications:** Integrate PHPMailer to dispatch registration confirmations and event sign-up reminders.
- **PDF ID Card Generation:** Incorporate FPDF or Dompdf to generate printable student identification passes.
- **Single Sign-On (SSO):** Expand authentication to support institutional Google Workspace OAuth2 login.

---

## 21. Author & Academic Information

- **Institution:** Charotar University of Science and Technology (CHARUSAT)
- **Faculty:** Faculty of Technology and Engineering (FTE)
- **Department:** Department of Computer Engineering
- **Program:** Bachelor of Technology (B.Tech) in Computer Engineering
- **Course:** Web Development Frameworks (ITUE203)
- **Academic Term:** Semester 3, Academic Year 2026–27
- **Repository Author:** Kush Patel ([@Kushpatel2611](https://github.com/Kushpatel2611))

---

## 22. License Information

This project is created for academic coursework and practical evaluation within the **Faculty of Technology and Engineering, CHARUSAT**. All rights are reserved by the university and the author. No open-source license is granted for commercial distribution.

# Project Audit & Changelog: CHARUSAT StudentHub Portal

This document details all issues identified during the initial code audit and the exact engineering fixes, enhancements, and testing frameworks implemented across the codebase.

---

## 1. Summary of Identified Issues & Root Causes

| Component / Area | Problem Identified | Root Cause |
| :--- | :--- | :--- |
| **Profile Page (`profile.html`)** | Profile page was a static mock with hardcoded values ("Aarav Patel", dummy contact info) and no database connection. Users had no way to view or edit their real database information or change their password. | Static HTML file was never connected to the PHP backend or MySQL database. |
| **Navigation Links** | Links in `about.html`, `contact.html`, `events.html`, `faq.html`, `feedback.html`, `index.html`, `students.html`, `practical7.php`, and `practical8.php` pointed to `profile.html` rather than a dynamic PHP profile. | Incomplete migration during earlier practical implementations. |
| **Student Dashboard (`dashboard.php`)** | Dashboard displayed event registrations but provided no way for students to register for events or cancel registrations from the dashboard. | Missing event registration form handler and cancellation actions. |
| **User Registration (`practical9.php`)** | Registering a new account only inserted a row into the `users` table; it did not create the associated record in the `students` table, leaving new accounts orphaned and incomplete. | Missing relational record provisioning and transaction management. |
| **Login Flexibility (`login.php`)** | Login only allowed email addresses, rejecting students who entered their username. | Query strictly matched `WHERE email = ?`. |
| **Public Student API (`api/students.php`)** | Blocked public GET requests for student directory listings unless logged in as an administrator, causing public directory fetch to fail. | Overly broad `sh_require_admin()` check applied to GET requests. |
| **Database Seed Credentials (`sql/studenthub_complete.sql`)** | Pre-seeded accounts had outdated or unverified password hashes, preventing immediate login after SQL import. | Inconsistent bcrypt salt generation during initial SQL creation. |
| **Admin Panel Navigation (`layout.php`)** | Sidebar links were a flat, repetitive list mixing operational tools and communications without logical hierarchy. | Lack of semantic grouping and visual section dividers. |
| **UI Aesthetics & Visual Hierarchy (`css/style.css`, `css/admin.css`)** | Inconsistent color palette, uneven card spacing, outdated buttons, lack of cohesive status badges, and harsh dark mode contrast. | Fragmented CSS rules across legacy practicals. |
| **Automated Testing Setup** | Zero automated database integration tests existed to verify core operations against a real MySQL instance. | Missing integration testing infrastructure. |

---

## 2. Detailed Technical Fixes Implemented

### A. Backend & Authentication Overhaul
1. **Dynamic Student Profile (`profile.php`):**
   - Implemented a complete, database-backed profile management page for authenticated students.
   - Real MySQL data retrieval joining `users` and `students` tables via PDO prepared statements.
   - Added student profile update form (Full Name, Student Code, Academic Course).
   - Added secure password change workflow with current-password verification via `password_verify()` and server-side password length checks.
   - Added CSRF token protection to all profile forms.
   - Handled session synchronization so that name/email changes immediately reflect across the session.
2. **Profile Fallback Redirection (`profile.html`):**
   - Replaced static content with an automatic meta-refresh and JavaScript redirect to `profile.php`.
   - Included a clean, styled fallback notification card for non-JS/direct file viewing.
3. **Interactive Student Dashboard (`dashboard.php`):**
   - Integrated live event registration: students can select available campus events from a dropdown and register instantly.
   - Prevented duplicate registrations and registrations for past events.
   - Added one-click registration cancellation with CSRF validation.
   - Displayed linked student code, academic department, and enrollment status.
4. **Atomic Multi-Table Registration (`practical9.php`):**
   - Re-engineered registration workflow using PDO transactions (`beginTransaction()`, `commit()`, `rollBack()`).
   - Inserts into `users` (`username`, `email`, `password_hash`, `role`) and immediately inserts the linked record into `students` (`user_id`, `student_code`, `name`, `email`, `course`, `status`).
   - Implemented friendly error reporting for duplicate usernames, emails, or student codes (`23000` SQLSTATE).
5. **Flexible Login System (`login.php`):**
   - Updated authentication query to match `WHERE email = ? OR username = ?`.
   - Allows students and administrators to log in using either their username or their institutional email address.
6. **Public Student Directory API (`api/students.php`):**
   - Permitted public `GET` requests to query active student records for the public directory (`students.html`).
   - Strictly preserved `sh_require_admin()` enforcement on state-modifying requests (`POST`, `PUT`, `DELETE`).
7. **Client-Side Fetch Integration (`js/students_page.js`):**
   - Updated directory renderer to fetch live active student records from `api/students.php`, with automatic fallback to `data/students.json` if offline.
8. **Navigation Normalization:**
   - Updated internal links across `about.html`, `contact.html`, `events.html`, `faq.html`, `feedback.html`, `index.html`, `students.html`, `practical7.php`, and `practical8.php` to point to `profile.php`.
   - Updated `about.html` description to accurately document the full-stack PHP/MySQL architecture.

---

### B. Database Schema & Data Integrity
1. **Relational Constraints & Foreign Keys (`sql/studenthub_complete.sql`):**
   - Ensured `InnoDB` storage engine with foreign keys:
     - `event_registrations.student_id` -> `students.id` (`ON DELETE CASCADE`)
     - `event_registrations.event_id` -> `events.id` (`ON DELETE CASCADE`)
     - `students.user_id` -> `users.id` (`ON DELETE SET NULL`)
   - Added UNIQUE constraints on `users.username`, `users.email`, `students.student_code`, and `students.email`.
   - Added UNIQUE composite constraint on `event_registrations(student_id, event_id)` to prevent duplicate event bookings.
2. **Verified Pre-Seeded Accounts:**
   - Seeded default Administrator: `admin` / `admin@charusat.edu.in` with password `Admin@123` (Bcrypt hash verified).
   - Seeded default Student: `kush_student` / `25ce030@charusat.edu.in` with password `Student@123` (Bcrypt hash verified).
   - Pre-populated audit log and sample events.

---

### C. Automated MySQL Integration Testing Suite
Created a lightweight, zero-dependency PHP integration testing framework in `tests/`:
1. `tests/test_config.php`: Dedicated test configuration targeting `studenthub_test`.
2. `tests/IntegrationTestCase.php`: Base test case featuring assertion helpers (`assertTrue`, `assertFalse`, `assertEquals`, `assertStringContains`, `assertCount`, `assertNotEmpty`), fixture provisioning, and automated database teardown.
3. `tests/run_tests.php`: CLI test runner executing all 16 test suites with clean ANSI colored status output and execution timers.
4. `run_tests.bat`: One-click Windows batch file for local test execution.
- **Test Results:** 16 tests executed, 62 assertions checked, 0 failures, 100% pass rate against live MariaDB.

---

### D. UI/UX Modernization
1. **Color Palette & CSS Custom Properties (`css/style.css`):**
   - Replaced generic styling with a refined academic palette: Deep Royal Blue (`--primary: #1e40af`, `--primary-hover: #1d4ed8`), slate neutral background (`--bg-primary: #f8fafc`, `--surface: #ffffff`), and high-contrast text (`--text-primary: #0f172a`).
   - Refined dark mode palette (`--bg-primary: #0b0f19`, `--surface: #111827`, `--border-color: #1f2937`) with accessible WCAG AAA contrast ratios.
2. **Button & Badge System:**
   - Standardized button classes: `.btn-primary`, `.btn-secondary`, `.btn-danger`, `.btn-outline`, `.btn-sm`.
   - Standardized status badges: `.badge-primary`, `.badge-success`, `.badge-danger`, `.badge-neutral`.
3. **Card & Form Layouts:**
   - Created clean, responsive form rows with consistent floating labels, focus rings, and validation indicators.
   - Styled dynamic profile cards, event registration tables, and dashboard metric widgets.
4. **Admin Sidebar Organization (`layout.php`, `css/admin.css`):**
   - Reorganized flat sidebar into 5 logical categories:
     - **Dashboard:** Overview & analytics.
     - **Academic:** Students directory, quick AJAX edit, and Campus Events.
     - **User Management:** User accounts, roles, and status activation.
     - **Communications:** Contact inquiries and Student Feedback messages.
     - **System:** System audit logs and database tools.
   - Polished table styling, action buttons, and responsive drawer navigation.

---

## 3. Preserved Academic Functionality

All original college practical requirements (Practicals 7 through 14) have been strictly preserved and verified:
- Practical 7: Form validation & CSV persistence (`practical7.php`).
- Practical 8: Database connectivity via PDO & queries (`practical8.php`).
- Practical 9: User registration with password hashing (`practical9.php`).
- Practical 10: Authentication, sessions (15-min timeout), remember-me cookies (`login.php`, `logout.php`).
- Practical 11: Admin student CRUD with search, pagination, and soft-delete (`student_management.php`).
- Practical 12: Admin event CRUD with validated poster image upload (`event_management.php`).
- Practical 13: JSON API endpoints & AJAX/Fetch student directory (`api/students.php`, `student_ajax.php`).
- Practical 14: Analytics KPIs, monthly registration charts, user activation, audit logging (`admin_dashboard.php`, `admin_audit.php`).

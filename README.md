# CHARUSAT StudentHub Portal

A full-stack student portal web application developed for the Web Development Frameworks (ITUE203) course at Charotar University of Science and Technology (CHARUSAT), Semester 3.

---

## About the Project

StudentHub is a campus web portal created to bring student services, event registrations, and administrative tasks into one place. 

The website has two user roles:
- **Students** can register for an account, view campus announcements, browse upcoming events, sign up for events, update their personal details, and change their password.
- **Administrators** can view portal statistics, manage student records, add and edit events with poster uploads, activate or deactivate accounts, review contact and feedback messages, and check system audit logs.

The project is built with plain PHP, MySQL, HTML, CSS, and vanilla JavaScript without using external backend frameworks.

---

## Features

### Student & Public Pages
- **Account Registration & Login:** Register with roll number, name, email, and password. Login works with either username or email.
- **Student Profile:** View linked database profile, edit name/course/student code, and change password (requires current password).
- **Student Dashboard:** View enrolled events and register for new campus events with duplicate check and one-click cancellation.
- **Events Directory:** Browse upcoming workshops, seminars, and tech events.
- **Student Directory:** Search and filter active students by department.
- **Contact & Feedback Forms:** Send inquiries and campus feedback; entries are saved and visible in the admin panel.
- **Theme Switcher:** Toggle between light and dark mode (saved in browser `localStorage`).

### Admin Panel
- **Overview Dashboard:** Cards showing total students, events, feedback count, and recent activity.
- **Student Management:** View paginated student records, search, filter by branch, edit details, or soft-delete and restore records.
- **Event Management:** Add, edit, and delete events with validated poster image uploads.
- **User Management:** Manage login accounts, toggle active/inactive status, and change roles.
- **Messages & Feedback:** Read and delete visitor inquiries and student feedback.
- **Audit Log:** Automatic log recording which admin performed what action and when.

---

## Technologies Used

- **Frontend:** HTML5, CSS3 (CSS Grid, Flexbox, custom variables), JavaScript (ES6, Fetch API)
- **Backend:** PHP 8.2 (PDO with prepared statements, session management, CSRF tokens, Bcrypt password hashing)
- **Database:** MySQL / MariaDB (InnoDB, foreign keys, unique constraints, transactions)
- **Server:** Apache (XAMPP) or PHP built-in web server with `router.php`

---

## Project Structure

```text
StudentHubPortal/
│
├── README.md                       # Main repository documentation
│
└── Student_Portal/                 # Main website project folder
    ├── api/                        # JSON API endpoints (students, events, contact, feedback)
    ├── assets/                     # Campus SVG illustrations and logos
    ├── css/                        # style.css (main site) and admin.css (admin panel)
    ├── data/                       # CSV submissions and JSON fallback data
    ├── js/                         # Frontend modules, form validation, theme switcher
    ├── sql/                        # Database scripts (studenthub_complete.sql) and ER diagram
    ├── tests/                      # Automated MySQL integration test suite
    ├── uploads/                    # Uploaded event posters (.htaccess protected)
    ├── index.html                  # Homepage
    ├── about.html                  # About CHARUSAT & portal overview
    ├── events.html                 # Public events page
    ├── students.html               # Public student directory
    ├── contact.html                # Contact inquiry page
    ├── feedback.html               # Student feedback page
    ├── faq.html                    # Campus FAQ page
    ├── login.php                   # Login page
    ├── logout.php                  # Logout handler
    ├── practical9.php              # Registration page
    ├── profile.php                 # Dynamic student profile page
    ├── dashboard.php               # Student dashboard with event sign-up
    ├── admin_dashboard.php         # Admin overview dashboard
    ├── student_management.php      # Admin student CRUD page
    ├── event_management.php        # Admin event CRUD page
    ├── admin_users.php             # Admin user management page
    ├── admin_messages.php          # Admin contact/feedback messages page
    ├── admin_faqs.php              # Admin FAQ manager
    ├── admin_audit.php             # Admin audit log viewer
    ├── db.php                      # PDO database connection helper
    ├── auth.php                    # Authentication, session check, and RBAC helpers
    ├── setup.php                   # Web-based database installer wizard
    ├── START_SERVER.bat            # Windows one-click server start script
    └── run_tests.bat               # Windows one-click test runner
```

---

## Setup and Installation

### Requirements
- **PHP 8.0 or newer** (with `pdo_mysql`, `mbstring`, `fileinfo` extensions enabled)
- **MySQL 5.7+ or MariaDB 10.4+**
- XAMPP is the easiest way to get PHP and MySQL together on Windows.

### 1. Database Setup
1. Start **MySQL** in XAMPP.
2. Open phpMyAdmin (`http://localhost/phpmyadmin/`) or open your MySQL command line.
3. Import the file `Student_Portal/sql/studenthub_complete.sql`.  
   This creates the `studenthub` database, all necessary tables, constraints, and pre-seeds sample data and demo accounts.
   
   *(Alternative: start the server and visit `http://localhost:8000/setup.php` to let the web wizard set up the tables).*

### 2. Database Configuration
By default, `Student_Portal/db.php` connects to `127.0.0.1:3306` with user `root` and an empty password (standard XAMPP settings).

If your MySQL uses a password or a different port (e.g. `3307`):
1. Copy `Student_Portal/config.example.php` to `Student_Portal/config.php`.
2. Edit `config.php` with your database credentials. `db.php` will automatically read from it.

---

## How to Run the Website Locally

You can run the project in either of two ways:

### Option 1: Using XAMPP (Apache)
1. Copy the `Student_Portal` folder to `C:\xampp\htdocs\Student_Portal`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open your browser and go to:
   ```text
   http://localhost/Student_Portal/
   ```

### Option 2: Using the PHP Built-in Server (No Apache Needed)
1. Make sure **MySQL** is running in XAMPP.
2. Open the `Student_Portal` folder in File Explorer and double-click `START_SERVER.bat`.  
   *(Or open a terminal in `Student_Portal` and run `php -S 127.0.0.1:8000 router.php`)*.
3. Open your browser and go to:
   ```text
   http://127.0.0.1:8000/
   ```

> **Note:** PHP pages must be opened through an HTTP server (Apache or the command above). If you double-click a `.php` file or open it with VS Code Live Server, the browser will show the PHP code instead of running it.

---

## Pre-Seeded Test Accounts

The database includes two default accounts for testing:

| Role | Username | Email | Password | Access |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin@charusat.edu.in` | `Admin@123` | Full access to the admin dashboard and management tools |
| **Student** | `kush_student` | `25ce030@charusat.edu.in` | `Student@123` | Access to student profile, dashboard, and event registration |

---

## Automated Tests

The project includes an automated MySQL integration test suite in `Student_Portal/tests/`.

### Running the tests:
- **Windows:** Double-click `Student_Portal/run_tests.bat`.
- **Command Line:**
  ```bash
  cd Student_Portal
  php tests/run_tests.php
  ```

The test runner runs 16 integration tests against a separate `studenthub_test` database to check database connections, schema integrity, registration, login/logout, password hashing, student and event CRUD, and role-based permissions. It does not touch your main project data.

---

## Academic Practicals Overview (WDF ITUE203)

This project was built step-by-step through the lab practicals:
- **Practicals 1–3:** Project planning, sitemap, wireframes, semantic HTML5 pages, and responsive CSS Grid/Flexbox styling.
- **Practicals 4–6:** JavaScript DOM manipulation, light/dark theme switching, regex form validation, and Fetch API for directory search and filtering.
- **Practicals 7–8:** Server-side PHP form processing with CSV storage, relational MySQL schema design, and PDO prepared statements.
- **Practicals 9–10:** User registration with Bcrypt hashing, secure session management, role-based access control, and 15-minute idle timeout.
- **Practicals 11–13:** Student management CRUD with pagination, event management with poster image validation, and RESTful JSON APIs.
- **Practical 14:** Admin dashboard with analytics cards, dynamic navigation, and system audit logging.

---

## Troubleshooting

- **Page displays PHP code as plain text:** Make sure you are accessing the site via `http://localhost/...` or `http://127.0.0.1:8000/`, not by opening the file directly with `file://`.
- **"Database connection failed":** Check if MySQL is running in XAMPP. If your MySQL runs on a non-default port like `3307`, copy `config.example.php` to `config.php` and set `'port' => 3307`.
- **"Table doesn't exist":** Import `Student_Portal/sql/studenthub_complete.sql` into phpMyAdmin, or run `setup.php` in your browser.
- **Poster upload error:** Make sure the folder `Student_Portal/uploads/event_posters/` exists and has write permissions.

---

## Author & Academic Information

- **Student:** Kush Godhaviya (25CE030)
- **Course:** Web Development Frameworks (ITUE203)
- **Program:** B.Tech Computer Engineering (Semester 3, 2026–27)
- **Institute:**  Chandubhai S Patel Institute of Science and technology(CSPIT) , CHARUSAT

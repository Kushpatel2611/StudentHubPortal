-- StudentHub Portal: COMPLETE database schema (Practicals 8-14)
-- Alternative to setup.php: import this ONE file in phpMyAdmin (Import tab).
-- It creates every table the PHP code needs (replaces sql/studenthub_practical8.sql).
-- WARNING: it DROPS and recreates the StudentHub tables, so existing rows are lost.
-- To keep your current data, use setup.php instead - it only adds what is missing.

CREATE DATABASE IF NOT EXISTS studenthub
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studenthub;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    remember_token_hash CHAR(64) NULL,
    remember_token_expires DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_remember (remember_token_hash)
) ENGINE=InnoDB;

CREATE TABLE students (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    course VARCHAR(100) NOT NULL,
    status ENUM('active', 'deleted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 100,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    poster VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE registrations (
    registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled') NOT NULL DEFAULT 'registered',
    CONSTRAINT fk_registrations_student FOREIGN KEY (student_id)
        REFERENCES students(student_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event FOREIGN KEY (event_id)
        REFERENCES events(event_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT uq_student_event UNIQUE (student_id, event_id),
    INDEX idx_registrations_event (event_id),
    INDEX idx_registrations_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- Sample students
INSERT INTO students (student_code, full_name, email, course) VALUES
('25CE030', 'Kush Godhaviya', '25ce030@charusat.edu.in', 'B.Tech Computer Engineering'),
('25CE031', 'Aarav Patel', 'aarav.patel@example.com', 'B.Tech Computer Engineering'),
('25IT014', 'Diya Shah', 'diya.shah@example.com', 'B.Tech Information Technology');

-- Sample events
INSERT INTO events (title, description, event_date, venue, capacity, category, status) VALUES
('Cybersecurity Awareness Workshop', 'Introduction to online safety and cybersecurity basics.', '2026-11-15', 'CSPIT Seminar Hall', 120, 'Workshop', 'open'),
('Web Development Bootcamp', 'Hands-on session on modern web development.', '2026-11-22', 'Computer Lab 2', 80, 'Workshop', 'open'),
('Student Innovation Meetup', 'Students share project ideas and prototypes.', '2026-12-05', 'CHARUSAT Auditorium', 150, 'Meetup', 'open');

-- Sample registrations
INSERT INTO registrations (student_id, event_id, status) VALUES
(1, 1, 'registered'),
(2, 1, 'registered'),
(1, 2, 'attended'),
(3, 3, 'registered');

-- Sample users (Default credentials: Admin@123 for admin, Student@123 for students)
INSERT INTO users (username, email, password, role, status) VALUES
('admin', 'admin@charusat.edu.in', '$2y$10$2eNh1M37czBbhOa4JWKFIeSSfIBei2xm9i72s/qVb9dYBmHaK7Dpy', 'admin', 'active'),
('kush_student', '25ce030@charusat.edu.in', '$2y$10$299GQJ9OJNaZLNkJZJ9axOvK6bZvlrLcTJL/ifGhxJ/ZKofvr.D7C', 'student', 'active'),
('aarav_student', 'aarav.patel@example.com', '$2y$10$299GQJ9OJNaZLNkJZJ9axOvK6bZvlrLcTJL/ifGhxJ/ZKofvr.D7C', 'student', 'active'),
('diya_student', 'diya.shah@example.com', '$2y$10$299GQJ9OJNaZLNkJZJ9axOvK6bZvlrLcTJL/ifGhxJ/ZKofvr.D7C', 'student', 'active');

-- Initial audit log entry
INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES
(1, 'system_initialized', 'Database initialized with sample academic data and seed accounts.', '127.0.0.1');

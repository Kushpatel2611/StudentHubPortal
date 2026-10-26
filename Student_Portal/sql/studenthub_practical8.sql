-- StudentHub Portal: Practical 8
-- Import this complete file in phpMyAdmin.
CREATE DATABASE IF NOT EXISTS studenthub
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studenthub;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS students;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE students (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    course VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 100,
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

-- Sample students
INSERT INTO students (student_code, full_name, email, course) VALUES
('25CE030', 'Kush Godhaviya', '25ce030@charusat.edu.in', 'B.Tech Computer Engineering'),
('25CE031', 'Aarav Patel', 'aarav.patel@example.com', 'B.Tech Computer Engineering'),
('25IT014', 'Diya Shah', 'diya.shah@example.com', 'B.Tech Information Technology');

-- Sample events
INSERT INTO events (title, description, event_date, venue, capacity) VALUES
('Cybersecurity Awareness Workshop', 'Introduction to online safety and cybersecurity basics.', '2026-11-15', 'CSPIT Seminar Hall', 120),
('Web Development Bootcamp', 'Hands-on session on modern web development.', '2026-11-22', 'Computer Lab 2', 80),
('Student Innovation Meetup', 'Students share project ideas and prototypes.', '2026-12-05', 'CHARUSAT Auditorium', 150);

-- Sample registrations
INSERT INTO registrations (student_id, event_id, status) VALUES
(1, 1, 'registered'),
(2, 1, 'registered'),
(1, 2, 'attended'),
(3, 3, 'registered');

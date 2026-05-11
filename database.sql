-- ============================================
-- Complaint & Feedback Reporting System DB
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================

CREATE DATABASE IF NOT EXISTS complaint_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE complaint_system;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'resident') NOT NULL DEFAULT 'resident',
    department VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Complaints Table
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_no VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('facilities', 'noise', 'safety', 'cleanliness', 'others') NOT NULL DEFAULT 'others',
    status ENUM('pending', 'in_progress', 'resolved', 'rejected') NOT NULL DEFAULT 'pending',
    photo VARCHAR(255) DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    resolution_photo VARCHAR(255) DEFAULT NULL,
    resolution_notes TEXT DEFAULT NULL,
    rating TINYINT DEFAULT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    complaint_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);

-- ============================================
-- Default Accounts
-- Admin password  : admin123
-- Staff password  : staff123
-- Residents       : register their own account via /register.php
-- ============================================
INSERT INTO users (full_name, email, password, role, department) VALUES
(
    'System Admin',
    'admin@system.com',
    '$2b$10$F8GO6qNohrRMfB.6SgTeGee13U0ZdT5Cn/ugpWDoSfTqIXCC4alcq',
    'admin',
    'Administration'
),
(
    'Juan dela Cruz',
    'staff@system.com',
    '$2b$10$5JxOiYEAOBgUx0NIrUOsY.717O/Shz/U8ZYiYyBdZkybhr5Jl1rr6',
    'staff',
    'Maintenance'
);
-- Admin  → admin@system.com  / admin123
-- Staff  → staff@system.com  / staff123
-- Residents register themselves at: /register.php

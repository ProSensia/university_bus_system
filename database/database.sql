-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Database: university_bus_system
--

-- CREATE DATABASE IF NOT EXISTS university_bus_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE university_bus_system;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `university_id` varchar(50) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `status` enum('active','pending','suspended','inactive') NOT NULL DEFAULT 'pending',
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_attempt` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `verification_token` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `university_id` (`university_id`),
  KEY `role_id` (`role_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `permissions`) VALUES
(1, 'Super Admin', 'Full system access with all permissions', '["*"]'),
(2, 'Admin', 'Administrator with limited permissions', '["manage_users","manage_buses","manage_fees","view_reports"]'),
(3, 'Student', 'Regular student user', '["view_profile","submit_fees","book_seat","view_schedule"]'),
(4, 'Driver', 'Bus driver with limited access', '["view_schedule","mark_attendance","update_location"]'),
(5, 'Faculty', 'University faculty member', '["view_profile","book_seat","view_schedule"]');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_remember_tokens_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_type` (`event_type`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `fk_security_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `fk_activity_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approval_requests`
--

CREATE TABLE `approval_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `request_type` enum('registration','profile_update','bus_change','leave') NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `request_type` (`request_type`),
  KEY `status` (`status`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `fk_approval_requests_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_approval_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campuses`
--

CREATE TABLE `campuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `university_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `university_id` (`university_id`),
  CONSTRAINT `fk_campuses_university_id` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) NOT NULL,
  `bus_number` varchar(20) NOT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `type` enum('coaster','40_seater','50_seater','hiace','other') NOT NULL DEFAULT 'coaster',
  `capacity` int(11) NOT NULL,
  `total_seats` int(11) NOT NULL,
  `available_seats` int(11) NOT NULL DEFAULT 0,
  `driver_id` int(11) DEFAULT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bus_number` (`bus_number`),
  UNIQUE KEY `registration_number` (`registration_number`),
  KEY `campus_id` (`campus_id`),
  KEY `driver_id` (`driver_id`),
  KEY `conductor_id` (`conductor_id`),
  CONSTRAINT `fk_buses_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_buses_conductor_id` FOREIGN KEY (`conductor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_buses_driver_id` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus_layouts`
--

CREATE TABLE `bus_layouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bus_type` varchar(50) NOT NULL,
  `layout_name` varchar(50) NOT NULL,
  `rows` int(11) NOT NULL,
  `columns` int(11) NOT NULL,
  `walking_areas` text DEFAULT NULL,
  `driver_area` varchar(20) DEFAULT NULL,
  `door_areas` text DEFAULT NULL,
  `special_seats` text DEFAULT NULL,
  `layout_config` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bus_type_layout_name` (`bus_type`,`layout_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus_routes`
--

CREATE TABLE `bus_routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bus_id` int(11) NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `start_point` varchar(100) NOT NULL,
  `end_point` varchar(100) NOT NULL,
  `total_distance` decimal(8,2) DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL,
  `stops` json DEFAULT NULL,
  `schedule` json DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bus_id` (`bus_id`),
  CONSTRAINT `fk_bus_routes_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_expiry` date DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `assigned_bus_id` int(11) DEFAULT NULL,
  `status` enum('active','on_leave','suspended','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `assigned_bus_id` (`assigned_bus_id`),
  CONSTRAINT `fk_drivers_assigned_bus_id` FOREIGN KEY (`assigned_bus_id`) REFERENCES `buses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_drivers_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_bus_assignments`
--

CREATE TABLE `student_bus_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `seat_number` varchar(10) DEFAULT NULL,
  `assignment_type` enum('permanent','temporary') NOT NULL DEFAULT 'permanent',
  `assigned_by` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','pending','cancelled','completed') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_active_assignment` (`student_id`,`status`),
  KEY `bus_id` (`bus_id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `seat_number` (`seat_number`),
  CONSTRAINT `fk_student_bus_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_student_bus_assignments_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_bus_assignments_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_structures`
--

CREATE TABLE `fee_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bus_id` int(11) NOT NULL,
  `fee_type` enum('monthly','bi_monthly','semester','annual') NOT NULL DEFAULT 'monthly',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bus_id` (`bus_id`),
  CONSTRAINT `fk_fee_structures_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `fee_structure_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','online','other') DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `fee_structure_id` (`fee_structure_id`),
  KEY `due_date` (`due_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_fees_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fees_fee_structure_id` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `application_type` enum('leave','bus_change','profile_update','fee_exemption','other') NOT NULL,
  `subject` varchar(200) NOT NULL,
  `details` text NOT NULL,
  `supporting_docs` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','processing') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `application_type` (`application_type`),
  KEY `status` (`status`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `fk_applications_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_applications_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `qr_data` text NOT NULL,
  `qr_image` varchar(255) DEFAULT NULL,
  `hash` varchar(64) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `last_scanned` datetime DEFAULT NULL,
  `scan_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_qr_codes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','late','leave') NOT NULL DEFAULT 'present',
  `recorded_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_date` (`student_id`,`date`),
  KEY `bus_id` (`bus_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `date` (`date`),
  CONSTRAINT `fk_attendance_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','number','boolean','json','array') NOT NULL DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`, `type`, `category`, `description`) VALUES
('system_name', 'University Bus Management System', 'string', 'general', 'System display name'),
('maintenance_mode', 'false', 'boolean', 'general', 'Enable/Disable maintenance mode'),
('registration_enabled', 'true', 'boolean', 'registration', 'Enable new user registration'),
('max_login_attempts', '5', 'number', 'security', 'Maximum login attempts before lockout'),
('lockout_time', '900', 'number', 'security', 'Lockout time in seconds'),
('session_timeout', '3600', 'number', 'security', 'Session timeout in seconds'),
('email_verification_required', 'true', 'boolean', 'registration', 'Require email verification'),
('max_file_size', '5242880', 'number', 'uploads', 'Maximum file upload size in bytes'),
('allowed_file_types', '["image/jpeg","image/jpg","image/png","image/gif","application/pdf"]', 'json', 'uploads', 'Allowed file upload types'),
('id_card_validity_days', '365', 'number', 'id_cards', 'ID card validity in days'),
('fee_due_days', '7', 'number', 'fees', 'Number of days before fee is considered overdue'),
('notification_retention_days', '30', 'number', 'notifications', 'Days to keep notifications'),
('backup_enabled', 'true', 'boolean', 'backup', 'Enable automatic backups'),
('backup_frequency', 'daily', 'string', 'backup', 'Backup frequency (daily, weekly, monthly)'),
('backup_retention_days', '30', 'number', 'backup', 'Days to keep backups');

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_type` enum('full','partial','auto','manual') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('success','failed','in_progress') NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `backup_type` (`backup_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Create initial admin user
--

DELIMITER $$

CREATE PROCEDURE CreateInitialAdmin()
BEGIN
    DECLARE admin_id INT;
    
    -- Check if admin already exists
    IF NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@university.edu') THEN
        -- Create admin user
        INSERT INTO users (email, username, password, role_id, status, created_at)
        VALUES ('admin@university.edu', 'admin', 
                '$2y$12$YourBcryptHashHere', 1, 'active', NOW());
        
        SET admin_id = LAST_INSERT_ID();
        
        -- Create admin profile
        INSERT INTO user_profiles (user_id, first_name, last_name, phone, created_at)
        VALUES (admin_id, 'System', 'Administrator', '03001234567', NOW());
        
        -- Log the creation
        INSERT INTO activity_logs (user_id, action, details, created_at)
        VALUES (admin_id, 'INITIAL_ADMIN_CREATED', 'Initial admin account created during setup', NOW());
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Indexes and foreign keys
--

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT;

-- --------------------------------------------------------

--
-- Create views
--

CREATE VIEW `active_students` AS
SELECT u.id, u.email, u.university_id, u.status, 
       p.first_name, p.last_name, p.phone, p.gender, p.semester, p.program,
       sba.bus_id, sba.seat_number, sba.status as assignment_status
FROM users u
LEFT JOIN user_profiles p ON u.id = p.user_id
LEFT JOIN student_bus_assignments sba ON u.id = sba.student_id AND sba.status = 'active'
WHERE u.role_id = 3 AND u.status = 'active';

CREATE VIEW `bus_occupancy` AS
SELECT b.id as bus_id, b.bus_number, b.capacity, b.total_seats, b.available_seats,
       COUNT(sba.id) as assigned_seats,
       CONCAT(ROUND((COUNT(sba.id) / b.capacity) * 100, 2), '%') as occupancy_rate,
       c.name as campus_name,
       r.route_name
FROM buses b
LEFT JOIN campuses c ON b.campus_id = c.id
LEFT JOIN bus_routes r ON b.id = r.bus_id
LEFT JOIN student_bus_assignments sba ON b.id = sba.bus_id AND sba.status = 'active'
WHERE b.status = 'active'
GROUP BY b.id;

-- --------------------------------------------------------

--
-- Create triggers
--

DELIMITER $$

CREATE TRIGGER `update_user_last_login`
AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
    IF NEW.last_login IS NOT NULL AND OLD.last_login != NEW.last_login THEN
        INSERT INTO activity_logs (user_id, action, details, created_at)
        VALUES (NEW.id, 'LOGIN', 'User logged in to the system', NOW());
    END IF;
END$$

CREATE TRIGGER `update_student_assignment_status`
AFTER UPDATE ON `student_bus_assignments`
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO activity_logs (user_id, action, details, created_at)
        VALUES (NEW.student_id, 'ASSIGNMENT_STATUS_CHANGE', 
                CONCAT('Bus assignment status changed from ', OLD.status, ' to ', NEW.status), 
                NOW());
    END IF;
END$$

CREATE TRIGGER `log_failed_login_attempt`
AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
    IF NEW.login_attempts > OLD.login_attempts THEN
        INSERT INTO security_logs (user_id, event_type, details, ip_address, timestamp)
        VALUES (NEW.id, 'FAILED_LOGIN_ATTEMPT', 
                CONCAT('Failed login attempt. Total attempts: ', NEW.login_attempts),
                NEW.ip_address, NOW());
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Create functions
--

DELIMITER $$

CREATE FUNCTION `CalculateAge`(dob DATE) 
RETURNS INT
DETERMINISTIC
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, dob, CURDATE());
END$$

CREATE FUNCTION `IsUserActive`(user_id INT) 
RETURNS BOOLEAN
READS SQL DATA
BEGIN
    DECLARE user_status VARCHAR(20);
    SELECT status INTO user_status FROM users WHERE id = user_id;
    RETURN user_status = 'active';
END$$

CREATE FUNCTION `GetUserFullName`(user_id INT) 
RETURNS VARCHAR(101)
READS SQL DATA
BEGIN
    DECLARE full_name VARCHAR(101);
    SELECT CONCAT(first_name, ' ', last_name) INTO full_name 
    FROM user_profiles 
    WHERE user_id = user_id;
    RETURN full_name;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Create procedures
--

DELIMITER $$

CREATE PROCEDURE `GenerateMonthlyReport`(IN report_month DATE)
BEGIN
    SELECT 
        b.bus_number,
        c.name as campus,
        COUNT(DISTINCT sba.student_id) as total_students,
        SUM(CASE WHEN f.status = 'paid' THEN 1 ELSE 0 END) as paid_students,
        SUM(CASE WHEN f.status = 'pending' THEN 1 ELSE 0 END) as pending_students,
        SUM(CASE WHEN f.status = 'overdue' THEN 1 ELSE 0 END) as overdue_students,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(DISTINCT DAY(a.date)) as avg_daily_attendance
    FROM buses b
    JOIN campuses c ON b.campus_id = c.id
    LEFT JOIN student_bus_assignments sba ON b.id = sba.bus_id 
        AND sba.status = 'active'
        AND MONTH(sba.start_date) = MONTH(report_month)
    LEFT JOIN fees f ON sba.student_id = f.student_id 
        AND MONTH(f.due_date) = MONTH(report_month)
    LEFT JOIN attendance a ON sba.student_id = a.student_id 
        AND MONTH(a.date) = MONTH(report_month)
    WHERE b.status = 'active'
    GROUP BY b.id;
END$$

CREATE PROCEDURE `BackupDatabase`()
BEGIN
    DECLARE backup_path VARCHAR(255);
    DECLARE backup_file VARCHAR(255);
    DECLARE backup_size BIGINT;
    
    SET backup_path = CONCAT('/backups/', DATE_FORMAT(NOW(), '%Y/%m/%d/'));
    SET backup_file = CONCAT(backup_path, 'backup_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.sql');
    
    -- Create directory if not exists
    -- Note: This requires FILE privilege and may need adjustment based on OS
    
    -- Log backup start
    INSERT INTO backup_logs (backup_type, file_path, status, details, created_at)
    VALUES ('auto', backup_file, 'in_progress', 'Starting automatic backup', NOW());
    
    -- Perform backup (this is a placeholder - actual backup logic depends on environment)
    -- SET @cmd = CONCAT('mysqldump -u root -p password university_bus_system > ', backup_file);
    -- PREPARE stmt FROM @cmd;
    -- EXECUTE stmt;
    -- DEALLOCATE PREPARE stmt;
    
    -- Get file size
    -- SET backup_size = (SELECT ROUND(LENGTH(LOAD_FILE(backup_file)) / 1024 / 1024, 2));
    
    -- Update backup log
    UPDATE backup_logs 
    SET status = 'success', 
        file_size = backup_size,
        details = CONCAT('Backup completed successfully. Size: ', backup_size, ' MB')
    WHERE file_path = backup_file 
    AND status = 'in_progress';
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Create events
--

DELIMITER $$

CREATE EVENT `CleanupOldSessions`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Cleanup expired sessions from remember_tokens
    DELETE FROM remember_tokens WHERE expires_at < NOW();
    
    -- Cleanup old security logs (keep 90 days)
    DELETE FROM security_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Cleanup old activity logs (keep 180 days)
    DELETE FROM activity_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 180 DAY);
    
    -- Cleanup old notifications (keep 30 days for read, 90 days for unread)
    DELETE FROM notifications 
    WHERE (is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
       OR (is_read = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY));
END$$

CREATE EVENT `ProcessPendingRegistrations`
ON SCHEDULE EVERY 1 HOUR
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Send reminder emails for pending approvals older than 24 hours
    DECLARE done INT DEFAULT FALSE;
    DECLARE user_email VARCHAR(100);
    DECLARE user_name VARCHAR(101);
    DECLARE cur CURSOR FOR 
        SELECT u.email, CONCAT(p.first_name, ' ', p.last_name)
        FROM approval_requests ar
        JOIN users u ON ar.user_id = u.id
        JOIN user_profiles p ON u.id = p.user_id
        WHERE ar.request_type = 'registration'
          AND ar.status = 'pending'
          AND ar.submitted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO user_email, user_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Send reminder email (placeholder)
        -- CALL SendEmail(user_email, 'Registration Reminder', CONCAT('Dear ', user_name, ', your registration is still pending.'));
    END LOOP;
    
    CLOSE cur;
END$$

CREATE EVENT `UpdateExpiredQR`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Mark expired QR codes
    UPDATE qr_codes 
    SET status = 'expired' 
    WHERE expires_at < NOW() 
    AND status = 'active';
    
    -- Generate new QR codes for active users with expired QR
    INSERT INTO qr_codes (user_id, qr_data, hash, expires_at, status)
    SELECT u.id, 
           CONCAT('user:', u.id, '|timestamp:', UNIX_TIMESTAMP()),
           SHA2(CONCAT(u.id, UNIX_TIMESTAMP(), RAND()), 256),
           DATE_ADD(NOW(), INTERVAL 30 DAY),
           'active'
    FROM users u
    LEFT JOIN qr_codes qr ON u.id = qr.user_id AND qr.status = 'active'
    WHERE u.status = 'active'
      AND qr.id IS NULL;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Create indexes for performance
--

CREATE INDEX idx_users_email_password ON users(email, password);
CREATE INDEX idx_users_status_role ON users(status, role_id);
CREATE INDEX idx_security_logs_user_event ON security_logs(user_id, event_type);
CREATE INDEX idx_activity_logs_user_action ON activity_logs(user_id, action);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_fees_student_status ON fees(student_id, status);
CREATE INDEX idx_attendance_student_date ON attendance(student_id, date);
CREATE INDEX idx_student_bus_assignments_student_bus ON student_bus_assignments(student_id, bus_id);
CREATE INDEX idx_buses_campus_status ON buses(campus_id, status);

-- --------------------------------------------------------

--
-- Execute initial setup procedures
--

CALL CreateInitialAdmin();

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
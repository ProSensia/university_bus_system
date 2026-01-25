-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 25, 2026 at 10:00 AM
-- Server version: 11.4.9-MariaDB-cll-lve-log
-- PHP Version: 8.3.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `prosdfwo_bus8-pak-austria-V1`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`prosdfwo`@`localhost` PROCEDURE `BackupDatabase` ()   BEGIN
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

CREATE DEFINER=`prosdfwo`@`localhost` PROCEDURE `CreateInitialAdmin` ()   BEGIN
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

CREATE DEFINER=`prosdfwo`@`localhost` PROCEDURE `GenerateMonthlyReport` (IN `report_month` DATE)   BEGIN
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

--
-- Functions
--
CREATE DEFINER=`prosdfwo`@`localhost` FUNCTION `CalculateAge` (`dob` DATE) RETURNS INT(11) DETERMINISTIC BEGIN
    RETURN TIMESTAMPDIFF(YEAR, dob, CURDATE());
END$$

CREATE DEFINER=`prosdfwo`@`localhost` FUNCTION `IsUserActive` (`user_id` INT) RETURNS TINYINT(1) READS SQL DATA BEGIN
    DECLARE user_status VARCHAR(20);
    SELECT status INTO user_status FROM users WHERE id = user_id;
    RETURN user_status = 'active';
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_students`
-- (See below for the actual view)
--
CREATE TABLE `active_students` (
`id` int(11)
,`email` varchar(100)
,`university_id` varchar(50)
,`status` enum('active','pending','suspended','inactive')
,`first_name` varchar(50)
,`last_name` varchar(50)
,`phone` varchar(20)
,`gender` enum('male','female','other')
,`semester` varchar(20)
,`program` varchar(100)
,`bus_id` int(11)
,`seat_number` varchar(10)
,`assignment_status` enum('active','pending','cancelled','completed')
);

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `timestamp`) VALUES
(1, 2, 'TEST_LOGIN', 'Test student logged in via test parameter', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:04:21'),
(2, 2, 'TEST_LOGIN', 'Test student logged in via test parameter', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:06:45');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `application_type` enum('leave','bus_change','profile_update','fee_exemption','other') NOT NULL,
  `subject` varchar(200) NOT NULL,
  `details` text NOT NULL,
  `supporting_docs` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','processing') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approval_requests`
--

CREATE TABLE `approval_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_type` enum('registration','profile_update','bus_change','leave') NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','late','leave') NOT NULL DEFAULT 'present',
  `recorded_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `backup_type` enum('full','partial','auto','manual') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('success','failed','in_progress') NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus_layouts`
--

CREATE TABLE `bus_layouts` (
  `id` int(11) NOT NULL,
  `bus_type` varchar(50) NOT NULL,
  `layout_name` varchar(50) NOT NULL,
  `rows` int(11) NOT NULL,
  `columns` int(11) NOT NULL,
  `walking_areas` text DEFAULT NULL,
  `driver_area` varchar(20) DEFAULT NULL,
  `door_areas` text DEFAULT NULL,
  `special_seats` text DEFAULT NULL,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_config`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `bus_occupancy`
-- (See below for the actual view)
--
CREATE TABLE `bus_occupancy` (
`bus_id` int(11)
,`bus_number` varchar(20)
,`capacity` int(11)
,`total_seats` int(11)
,`available_seats` int(11)
,`assigned_seats` bigint(21)
,`occupancy_rate` varchar(29)
,`campus_name` varchar(100)
,`route_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `bus_routes`
--

CREATE TABLE `bus_routes` (
  `id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `start_point` varchar(100) NOT NULL,
  `end_point` varchar(100) NOT NULL,
  `total_distance` decimal(8,2) DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL,
  `stops` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`stops`)),
  `schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule`)),
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campuses`
--

CREATE TABLE `campuses` (
  `id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_expiry` date DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `assigned_bus_id` int(11) DEFAULT NULL,
  `status` enum('active','on_leave','suspended','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_structure_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','online','other') DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_structures`
--

CREATE TABLE `fee_structures` (
  `id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `fee_type` enum('monthly','bi_monthly','semester','annual') NOT NULL DEFAULT 'monthly',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `qr_data` text NOT NULL,
  `qr_image` varchar(255) DEFAULT NULL,
  `hash` varchar(64) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `last_scanned` datetime DEFAULT NULL,
  `scan_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `permissions`, `created_at`) VALUES
(1, 'Super Admin', 'Full system access with all permissions', '[\"*\"]', '2026-01-25 11:46:48'),
(2, 'Admin', 'Administrator with limited permissions', '[\"manage_users\",\"manage_buses\",\"manage_fees\",\"view_reports\"]', '2026-01-25 11:46:48'),
(3, 'Student', 'Regular student user', '[\"view_profile\",\"submit_fees\",\"book_seat\",\"view_schedule\"]', '2026-01-25 11:46:48'),
(4, 'Driver', 'Bus driver with limited access', '[\"view_schedule\",\"mark_attendance\",\"update_location\"]', '2026-01-25 11:46:48'),
(5, 'Faculty', 'University faculty member', '[\"view_profile\",\"book_seat\",\"view_schedule\"]', '2026-01-25 11:46:48');

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`id`, `user_id`, `event_type`, `details`, `ip_address`, `user_agent`, `timestamp`) VALUES
(1, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 06:59:23'),
(2, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 06:59:28'),
(3, NULL, 'PAGE_VISIT', 'Landing page accessed', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 06:59:31'),
(4, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 06:59:31'),
(5, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 06:59:34'),
(6, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 07:01:35'),
(7, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 07:01:41'),
(8, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 07:01:43'),
(9, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 07:01:45'),
(10, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.186.234', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 07:02:37'),
(11, NULL, 'PAGE_VISIT', 'Landing page accessed', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 07:02:39'),
(12, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '182.177.186.234', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 07:02:39'),
(13, NULL, 'PAGE_VISIT', 'Landing page accessed', '98.84.1.175', '{\"user_agent\":\"Unknown\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}', '2026-01-25 08:36:59'),
(14, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '98.84.1.175', '{\"user_agent\":\"Unknown\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}', '2026-01-25 08:36:59'),
(15, NULL, 'PAGE_VISIT', 'Landing page accessed', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:52:03'),
(16, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:52:03'),
(17, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:52:08'),
(18, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:53:04'),
(19, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:53:40'),
(20, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:53:44'),
(21, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:57:21'),
(22, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:57:50'),
(23, NULL, 'REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:57:59'),
(24, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.140.24', '182.177.140.24', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:58:01'),
(25, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 116.71.175.86', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 08:59:04'),
(26, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 116.71.175.86', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:04:17'),
(27, 2, 'TEST_LOGIN', 'Test student auto-login accessed', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:04:21'),
(28, NULL, 'PAGE_VISIT', 'Landing page accessed', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:06:32'),
(29, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:06:32'),
(30, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 116.71.175.86', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:06:41'),
(31, 2, 'TEST_LOGIN', 'Test student auto-login accessed', '116.71.175.86', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:06:45'),
(32, NULL, 'PAGE_VISIT', 'Landing page accessed', '156.146.55.172', '{\"user_agent\":\"Mozilla\\/5.0 (X11; Linux x86_64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Linux\"}', '2026-01-25 09:09:14'),
(33, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '156.146.55.172', '{\"user_agent\":\"Mozilla\\/5.0 (X11; Linux x86_64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Linux\"}', '2026-01-25 09:09:14'),
(34, NULL, 'PAGE_VISIT', 'Landing page accessed', '95.181.233.132', '{\"user_agent\":\"Mozilla\\/5.0 (X11; Linux x86_64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Linux\"}', '2026-01-25 09:10:49'),
(35, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '95.181.233.132', '{\"user_agent\":\"Mozilla\\/5.0 (X11; Linux x86_64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"browser\":\"Google Chrome\",\"platform\":\"Linux\"}', '2026-01-25 09:10:49'),
(36, NULL, 'PAGE_VISIT', 'Landing page accessed', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:40:25'),
(37, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:40:25'),
(38, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.159.45', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:40:29'),
(39, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.159.45', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:40:35'),
(40, NULL, 'PAGE_VISIT', 'Landing page accessed', '34.28.97.105', '{\"user_agent\":\"Mozilla\\/5.0 (compatible; CMS-Checker\\/1.0; +https:\\/\\/example.com)\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}', '2026-01-25 09:45:00'),
(41, NULL, 'PAGE_VISIT', 'Landing page accessed', '34.28.97.105', '{\"user_agent\":\"Mozilla\\/5.0 (compatible; CMS-Checker\\/1.0; +https:\\/\\/example.com)\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}', '2026-01-25 09:45:00'),
(42, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '34.28.97.105', '{\"user_agent\":\"Mozilla\\/5.0 (compatible; CMS-Checker\\/1.0; +https:\\/\\/example.com)\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}', '2026-01-25 09:45:00'),
(43, NULL, 'PAGE_LOAD_SUCCESS', 'Landing page loaded successfully', '34.28.97.105', '{\"user_agent\":\"Mozilla\\/5.0 (compatible; CMS-Checker\\/1.0; +https:\\/\\/example.com)\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}', '2026-01-25 09:45:00'),
(44, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.159.45', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:46:54'),
(45, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.159.45', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:49:00'),
(46, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.159.45', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:51:22'),
(47, NULL, 'LOGIN_PAGE_ACCESS', 'Login page accessed from IP: 182.177.159.45', '182.177.159.45', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Google Chrome\",\"platform\":\"Windows\"}', '2026-01-25 09:51:40');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','number','boolean','json','array') NOT NULL DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `type`, `category`, `description`, `created_at`, `updated_at`) VALUES
(1, 'system_name', 'University Bus Management System', 'string', 'general', 'System display name', '2026-01-25 11:46:48', NULL),
(2, 'maintenance_mode', 'false', 'boolean', 'general', 'Enable/Disable maintenance mode', '2026-01-25 11:46:48', NULL),
(3, 'registration_enabled', 'true', 'boolean', 'registration', 'Enable new user registration', '2026-01-25 11:46:48', NULL),
(4, 'max_login_attempts', '5', 'number', 'security', 'Maximum login attempts before lockout', '2026-01-25 11:46:48', NULL),
(5, 'lockout_time', '900', 'number', 'security', 'Lockout time in seconds', '2026-01-25 11:46:48', NULL),
(6, 'session_timeout', '3600', 'number', 'security', 'Session timeout in seconds', '2026-01-25 11:46:48', NULL),
(7, 'email_verification_required', 'true', 'boolean', 'registration', 'Require email verification', '2026-01-25 11:46:48', NULL),
(8, 'max_file_size', '5242880', 'number', 'uploads', 'Maximum file upload size in bytes', '2026-01-25 11:46:48', NULL),
(9, 'allowed_file_types', '[\"image/jpeg\",\"image/jpg\",\"image/png\",\"image/gif\",\"application/pdf\"]', 'json', 'uploads', 'Allowed file upload types', '2026-01-25 11:46:48', NULL),
(10, 'id_card_validity_days', '365', 'number', 'id_cards', 'ID card validity in days', '2026-01-25 11:46:48', NULL),
(11, 'fee_due_days', '7', 'number', 'fees', 'Number of days before fee is considered overdue', '2026-01-25 11:46:48', NULL),
(12, 'notification_retention_days', '30', 'number', 'notifications', 'Days to keep notifications', '2026-01-25 11:46:48', NULL),
(13, 'backup_enabled', 'true', 'boolean', 'backup', 'Enable automatic backups', '2026-01-25 11:46:48', NULL),
(14, 'backup_frequency', 'daily', 'string', 'backup', 'Backup frequency (daily, weekly, monthly)', '2026-01-25 11:46:48', NULL),
(15, 'backup_retention_days', '30', 'number', 'backup', 'Days to keep backups', '2026-01-25 11:46:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_bus_assignments`
--

CREATE TABLE `student_bus_assignments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `seat_number` varchar(10) DEFAULT NULL,
  `assignment_type` enum('permanent','temporary') NOT NULL DEFAULT 'permanent',
  `assigned_by` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','pending','cancelled','completed') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `student_bus_assignments`
--
DELIMITER $$
CREATE TRIGGER `update_student_assignment_status` AFTER UPDATE ON `student_bus_assignments` FOR EACH ROW BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO activity_logs (user_id, action, details, created_at)
        VALUES (NEW.student_id, 'ASSIGNMENT_STATUS_CHANGE', 
                CONCAT('Bus assignment status changed from ', OLD.status, ' to ', NEW.status), 
                NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password`, `university_id`, `role_id`, `status`, `login_attempts`, `locked_until`, `last_attempt`, `last_login`, `verification_token`, `created_at`, `updated_at`, `ip_address`) VALUES
(1, 'admin@university.edu', 'admin', '$2y$12$YourBcryptHashHere', NULL, 1, 'active', 0, NULL, NULL, NULL, NULL, '2026-01-25 11:46:48', NULL, NULL),
(2, 'test@student.edu', 'teststudent', '$2y$10$3ZF.jsKdBVhYazBDRVn.Ru4dbxHyVugfUSkVBHV8Iuz653zaGAlvK', NULL, 3, 'active', 0, NULL, NULL, NULL, NULL, '2026-01-25 09:04:21', NULL, NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `log_failed_login_attempt` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.login_attempts > OLD.login_attempts THEN
        INSERT INTO security_logs (user_id, event_type, details, ip_address, timestamp)
        VALUES (NEW.id, 'FAILED_LOGIN_ATTEMPT', 
                CONCAT('Failed login attempt. Total attempts: ', NEW.login_attempts),
                NEW.ip_address, NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_user_last_login` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.last_login IS NOT NULL AND OLD.last_login != NEW.last_login THEN
        INSERT INTO activity_logs (user_id, action, details, created_at)
        VALUES (NEW.id, 'LOGIN', 'User logged in to the system', NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `gender`, `date_of_birth`, `address`, `emergency_contact`, `blood_group`, `profile_photo`, `department`, `semester`, `program`, `created_at`, `updated_at`) VALUES
(1, 1, 'System', 'Administrator', '03001234567', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-25 11:46:48', NULL),
(2, 2, 'Test', 'Student', '03001234567', 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-25 09:04:21', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `timestamp` (`timestamp`),
  ADD KEY `idx_activity_logs_user_action` (`user_id`,`action`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `application_type` (`application_type`),
  ADD KEY `status` (`status`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `request_type` (`request_type`),
  ADD KEY `status` (`status`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_date` (`student_id`,`date`),
  ADD KEY `bus_id` (`bus_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `date` (`date`),
  ADD KEY `idx_attendance_student_date` (`student_id`,`date`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `backup_type` (`backup_type`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `buses`
--
ALTER TABLE `buses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bus_number` (`bus_number`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD KEY `campus_id` (`campus_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `conductor_id` (`conductor_id`),
  ADD KEY `idx_buses_campus_status` (`campus_id`,`status`);

--
-- Indexes for table `bus_layouts`
--
ALTER TABLE `bus_layouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bus_type_layout_name` (`bus_type`,`layout_name`);

--
-- Indexes for table `bus_routes`
--
ALTER TABLE `bus_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bus_id` (`bus_id`);

--
-- Indexes for table `campuses`
--
ALTER TABLE `campuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `university_id` (`university_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `assigned_bus_id` (`assigned_bus_id`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_structure_id` (`fee_structure_id`),
  ADD KEY `due_date` (`due_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_fees_student_status` (`student_id`,`status`);

--
-- Indexes for table `fee_structures`
--
ALTER TABLE `fee_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bus_id` (`bus_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `timestamp` (`timestamp`),
  ADD KEY `idx_security_logs_user_event` (`user_id`,`event_type`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `student_bus_assignments`
--
ALTER TABLE `student_bus_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_active_assignment` (`student_id`,`status`),
  ADD KEY `bus_id` (`bus_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `seat_number` (`seat_number`),
  ADD KEY `idx_student_bus_assignments_student_bus` (`student_id`,`bus_id`);

--
-- Indexes for table `universities`
--
ALTER TABLE `universities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `university_id` (`university_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_users_email_password` (`email`,`password`),
  ADD KEY `idx_users_status_role` (`status`,`role_id`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_requests`
--
ALTER TABLE `approval_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `buses`
--
ALTER TABLE `buses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bus_layouts`
--
ALTER TABLE `bus_layouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bus_routes`
--
ALTER TABLE `bus_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campuses`
--
ALTER TABLE `campuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fees`
--
ALTER TABLE `fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_structures`
--
ALTER TABLE `fee_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `student_bus_assignments`
--
ALTER TABLE `student_bus_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `universities`
--
ALTER TABLE `universities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- --------------------------------------------------------

--
-- Structure for view `active_students`
--
DROP TABLE IF EXISTS `active_students`;

CREATE ALGORITHM=UNDEFINED DEFINER=`prosdfwo`@`localhost` SQL SECURITY DEFINER VIEW `active_students`  AS SELECT `u`.`id` AS `id`, `u`.`email` AS `email`, `u`.`university_id` AS `university_id`, `u`.`status` AS `status`, `p`.`first_name` AS `first_name`, `p`.`last_name` AS `last_name`, `p`.`phone` AS `phone`, `p`.`gender` AS `gender`, `p`.`semester` AS `semester`, `p`.`program` AS `program`, `sba`.`bus_id` AS `bus_id`, `sba`.`seat_number` AS `seat_number`, `sba`.`status` AS `assignment_status` FROM ((`users` `u` left join `user_profiles` `p` on(`u`.`id` = `p`.`user_id`)) left join `student_bus_assignments` `sba` on(`u`.`id` = `sba`.`student_id` and `sba`.`status` = 'active')) WHERE `u`.`role_id` = 3 AND `u`.`status` = 'active' ;

-- --------------------------------------------------------

--
-- Structure for view `bus_occupancy`
--
DROP TABLE IF EXISTS `bus_occupancy`;

CREATE ALGORITHM=UNDEFINED DEFINER=`prosdfwo`@`localhost` SQL SECURITY DEFINER VIEW `bus_occupancy`  AS SELECT `b`.`id` AS `bus_id`, `b`.`bus_number` AS `bus_number`, `b`.`capacity` AS `capacity`, `b`.`total_seats` AS `total_seats`, `b`.`available_seats` AS `available_seats`, count(`sba`.`id`) AS `assigned_seats`, concat(round(count(`sba`.`id`) / `b`.`capacity` * 100,2),'%') AS `occupancy_rate`, `c`.`name` AS `campus_name`, `r`.`route_name` AS `route_name` FROM (((`buses` `b` left join `campuses` `c` on(`b`.`campus_id` = `c`.`id`)) left join `bus_routes` `r` on(`b`.`id` = `r`.`bus_id`)) left join `student_bus_assignments` `sba` on(`b`.`id` = `sba`.`bus_id` and `sba`.`status` = 'active')) WHERE `b`.`status` = 'active' GROUP BY `b`.`id` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_applications_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD CONSTRAINT `fk_approval_requests_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_approval_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `buses`
--
ALTER TABLE `buses`
  ADD CONSTRAINT `fk_buses_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_buses_conductor_id` FOREIGN KEY (`conductor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_buses_driver_id` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bus_routes`
--
ALTER TABLE `bus_routes`
  ADD CONSTRAINT `fk_bus_routes_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `campuses`
--
ALTER TABLE `campuses`
  ADD CONSTRAINT `fk_campuses_university_id` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `fk_drivers_assigned_bus_id` FOREIGN KEY (`assigned_bus_id`) REFERENCES `buses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drivers_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fk_fees_fee_structure_id` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fees_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fee_structures`
--
ALTER TABLE `fee_structures`
  ADD CONSTRAINT `fk_fee_structures_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `fk_qr_codes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_tokens_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD CONSTRAINT `fk_security_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_bus_assignments`
--
ALTER TABLE `student_bus_assignments`
  ADD CONSTRAINT `fk_student_bus_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_student_bus_assignments_bus_id` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_bus_assignments_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_user_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`prosdfwo`@`localhost` EVENT `CleanupOldSessions` ON SCHEDULE EVERY 1 DAY STARTS '2026-01-25 11:46:48' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
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

CREATE DEFINER=`prosdfwo`@`localhost` EVENT `ProcessPendingRegistrations` ON SCHEDULE EVERY 1 HOUR STARTS '2026-01-25 11:46:48' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
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

CREATE DEFINER=`prosdfwo`@`localhost` EVENT `UpdateExpiredQR` ON SCHEDULE EVERY 1 DAY STARTS '2026-01-25 11:46:48' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

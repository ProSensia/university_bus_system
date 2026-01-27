-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 25, 2026 at 02:08 AM
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
-- Database: `prosdfwo_bus-8-pak-austria`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_username` varchar(50) NOT NULL,
  `action` varchar(255) NOT NULL,
  `target_student` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_username`, `action`, `target_student`, `ip_address`, `timestamp`) VALUES
(1, 'momin', 'Voucher rejected for Momin Khan (Months: January 26,February 26)', 'Momin Khan', '182.177.184.221', '2026-01-24 12:36:23'),
(2, 'momin', 'Voucher rejected for Momin Khan (Months: January 26,February 26)', 'Momin Khan', '182.177.184.221', '2026-01-24 12:41:59'),
(3, 'momin', 'Voucher rejected for Momin Khan (Months: January 26,February 26)', 'Momin Khan', '182.177.184.221', '2026-01-24 12:42:05'),
(4, 'momin', 'Voucher approved for Momin Khan (Months: January 26,February 26)', 'Momin Khan', '182.177.184.221', '2026-01-24 12:48:49'),
(5, 'momin', 'Voucher approved for Momin Khan (Months: January 26,February 26)', 'Momin Khan', '182.177.184.221', '2026-01-24 12:48:53'),
(6, 'momin', 'Voucher approved for Momin Khan (Months: January 26,February 26)', 'Momin Khan', '182.177.184.221', '2026-01-24 12:56:42'),
(7, 'momin', 'Voucher approved for Harbaz Khan Jadoon (Months: January 26,February 26)', 'Harbaz Khan Jadoon', '182.177.184.221', '2026-01-24 13:32:59'),
(8, 'momin', 'Voucher approved for Umama Jadoon (Months: January 26,February 26)', 'Umama Jadoon', '182.177.184.221', '2026-01-24 13:39:55'),
(9, 'momin', 'Voucher approved for Umama Jadoon (Months: January 26,February 26)', 'Umama Jadoon', '182.177.184.221', '2026-01-24 13:41:53'),
(10, 'momin', 'Voucher approved for Umama Jadoon (Months: January 26,February 26)', 'Umama Jadoon', '182.177.184.221', '2026-01-24 13:44:20'),
(11, 'momin', 'Voucher approved for Umama Jadoon (Months: January 26,February 26)', 'Umama Jadoon', '182.177.184.221', '2026-01-24 13:47:34'),
(12, 'momin', 'Voucher approved for Umama Jadoon (Months: January 26,February 26)', 'Umama Jadoon', '182.177.184.221', '2026-01-24 13:54:39'),
(13, 'momin', 'Voucher approved for Umama Jadoon (Months: January 26,February 26)', 'Umama Jadoon', '182.177.184.221', '2026-01-24 14:07:55'),
(14, 'momin', 'Voucher approved for Umama Jadoon (Months: January 26,February 26)', 'Umama Jadoon', '182.177.184.221', '2026-01-24 14:07:58'),
(15, 'momin', 'Voucher approved for Ibrahim khan (Months: January 26,February 26)', 'Ibrahim khan', '182.177.129.182', '2026-01-25 02:05:55'),
(16, 'momin', 'Voucher approved for Saffiullah Ahmed Khan (Months: January 26,February 26)', 'Saffiullah Ahmed Khan', '182.177.129.182', '2026-01-25 02:06:11'),
(17, 'momin', 'Voucher approved for Rafia Qureshi (Months: January 26)', 'Rafia Qureshi', '182.177.129.182', '2026-01-25 02:06:17'),
(18, 'momin', 'Voucher approved for Mashab Jadoon (Months: January 26,February 26)', 'Mashab Jadoon', '182.177.129.182', '2026-01-25 02:06:20');

-- --------------------------------------------------------

--
-- Table structure for table `fee_payments`
--

CREATE TABLE `fee_payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `month_id` int(11) NOT NULL,
  `status` enum('Pending','Submitted','Verified','Paid') DEFAULT 'Pending'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `fee_payments`
--

INSERT INTO `fee_payments` (`id`, `student_id`, `month_id`, `status`) VALUES
(1, 1, 9, 'Submitted'),
(2, 2, 9, 'Submitted'),
(3, 3, 9, 'Submitted'),
(4, 4, 9, 'Pending'),
(5, 5, 9, 'Submitted'),
(6, 6, 9, 'Submitted'),
(7, 7, 9, 'Submitted'),
(8, 8, 9, 'Submitted'),
(9, 9, 9, 'Pending'),
(10, 10, 9, 'Pending'),
(11, 11, 9, 'Pending'),
(12, 12, 9, 'Pending'),
(13, 13, 9, 'Pending'),
(14, 14, 9, 'Pending'),
(15, 15, 9, 'Pending'),
(16, 16, 9, 'Pending'),
(17, 17, 9, 'Pending'),
(18, 18, 9, 'Submitted'),
(20, 20, 9, 'Submitted'),
(21, 21, 9, 'Pending'),
(22, 22, 9, 'Pending'),
(23, 23, 9, 'Submitted'),
(24, 24, 9, 'Pending'),
(25, 25, 9, 'Pending'),
(26, 26, 9, 'Pending'),
(27, 27, 9, 'Pending'),
(28, 28, 9, 'Pending'),
(29, 29, 9, 'Pending'),
(30, 30, 9, 'Pending'),
(31, 31, 9, 'Pending'),
(32, 32, 9, 'Submitted'),
(33, 33, 9, 'Submitted'),
(34, 1, 10, 'Submitted'),
(35, 2, 10, 'Submitted'),
(36, 3, 10, 'Submitted'),
(37, 4, 10, 'Submitted'),
(38, 5, 10, 'Submitted'),
(39, 6, 10, 'Submitted'),
(40, 7, 10, 'Submitted'),
(41, 8, 10, 'Submitted'),
(42, 9, 10, 'Submitted'),
(43, 10, 10, 'Submitted'),
(44, 11, 10, 'Submitted'),
(45, 12, 10, 'Submitted'),
(46, 13, 10, 'Submitted'),
(47, 14, 10, 'Submitted'),
(48, 15, 10, 'Submitted'),
(49, 16, 10, 'Submitted'),
(50, 17, 10, 'Submitted'),
(51, 18, 10, 'Submitted'),
(53, 20, 10, 'Submitted'),
(54, 21, 10, 'Submitted'),
(55, 22, 10, 'Submitted'),
(56, 23, 10, 'Submitted'),
(57, 24, 10, 'Submitted'),
(58, 25, 10, 'Submitted'),
(59, 26, 10, 'Submitted'),
(60, 27, 10, 'Submitted'),
(61, 28, 10, 'Submitted'),
(62, 29, 10, 'Submitted'),
(63, 30, 10, 'Submitted'),
(64, 31, 10, 'Submitted'),
(65, 32, 10, 'Submitted'),
(66, 33, 10, 'Submitted'),
(67, 1, 11, 'Submitted'),
(68, 2, 11, 'Submitted'),
(69, 3, 11, 'Submitted'),
(70, 4, 11, 'Submitted'),
(71, 5, 11, 'Submitted'),
(72, 6, 11, 'Submitted'),
(73, 7, 11, 'Submitted'),
(74, 8, 11, 'Submitted'),
(75, 9, 11, 'Submitted'),
(76, 10, 11, 'Submitted'),
(77, 11, 11, 'Submitted'),
(78, 12, 11, 'Submitted'),
(79, 13, 11, 'Submitted'),
(80, 14, 11, 'Submitted'),
(81, 15, 11, 'Submitted'),
(82, 16, 11, 'Submitted'),
(83, 17, 11, 'Submitted'),
(84, 18, 11, 'Submitted'),
(86, 20, 11, 'Submitted'),
(87, 21, 11, 'Submitted'),
(88, 22, 11, 'Submitted'),
(89, 23, 11, 'Submitted'),
(90, 24, 11, 'Submitted'),
(91, 25, 11, 'Submitted'),
(92, 26, 11, 'Submitted'),
(93, 27, 11, 'Submitted'),
(94, 28, 11, 'Submitted'),
(95, 29, 11, 'Submitted'),
(96, 30, 11, 'Submitted'),
(97, 31, 11, 'Submitted'),
(98, 32, 11, 'Submitted'),
(99, 33, 11, 'Submitted'),
(100, 1, 12, 'Submitted'),
(101, 2, 12, 'Submitted'),
(102, 3, 12, 'Submitted'),
(103, 4, 12, 'Submitted'),
(104, 5, 12, 'Submitted'),
(105, 6, 12, 'Submitted'),
(106, 7, 12, 'Submitted'),
(107, 8, 12, 'Submitted'),
(108, 9, 12, 'Submitted'),
(109, 10, 12, 'Submitted'),
(110, 11, 12, 'Submitted'),
(111, 12, 12, 'Submitted'),
(112, 13, 12, 'Submitted'),
(113, 14, 12, 'Submitted'),
(114, 15, 12, 'Submitted'),
(115, 16, 12, 'Submitted'),
(116, 17, 12, 'Submitted'),
(117, 18, 12, 'Submitted'),
(119, 20, 12, 'Submitted'),
(120, 21, 12, 'Submitted'),
(121, 22, 12, 'Submitted'),
(122, 23, 12, 'Submitted'),
(123, 24, 12, 'Submitted'),
(124, 25, 12, 'Submitted'),
(125, 26, 12, 'Submitted'),
(126, 27, 12, 'Submitted'),
(127, 28, 12, 'Submitted'),
(128, 29, 12, 'Submitted'),
(129, 30, 12, 'Submitted'),
(130, 31, 12, 'Submitted'),
(131, 32, 12, 'Submitted'),
(132, 33, 12, 'Submitted'),
(182, 2, 14, 'Submitted'),
(181, 2, 13, 'Submitted'),
(180, 31, 13, 'Submitted'),
(179, 6, 14, 'Submitted'),
(178, 6, 13, 'Submitted'),
(177, 15, 14, 'Submitted'),
(176, 15, 13, 'Submitted'),
(175, 3, 14, 'Submitted'),
(174, 3, 13, 'Submitted'),
(173, 8, 14, 'Submitted'),
(172, 8, 13, 'Submitted'),
(171, 1, 14, 'Submitted'),
(170, 1, 13, 'Submitted');

-- --------------------------------------------------------

--
-- Table structure for table `fee_vouchers`
--

CREATE TABLE `fee_vouchers` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `months_applied` varchar(100) NOT NULL,
  `voucher_image` varchar(255) NOT NULL,
  `submission_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_date` datetime DEFAULT NULL,
  `mac_address` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `location_data` text DEFAULT NULL,
  `device_info` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `fee_vouchers`
--

INSERT INTO `fee_vouchers` (`id`, `student_id`, `months_applied`, `voucher_image`, `submission_date`, `status`, `admin_notes`, `processed_date`, `mac_address`, `ip_address`, `location_data`, `device_info`) VALUES
(7, 8, 'January 26,February 26', 'voucher_B24F0080EE001_1769279081.jpg', '2026-01-24 13:24:41', 'approved', 'Approved...', '2026-01-24 13:32:59', 'Unknown', '39.62.170.109', NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Mobile Safari\\/537.36\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}'),
(6, 1, 'January 26,February 26', 'voucher_B22F1181AI056_1769277003.jpg', '2026-01-24 12:50:03', 'approved', 'Approved...', '2026-01-24 12:56:42', 'Unknown', '182.177.184.221', NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}'),
(8, 3, 'January 26,February 26', 'voucher_B24S0950AI005_1769279748.jpg', '2026-01-24 13:35:48', 'approved', 'Approved...', '2026-01-24 14:07:58', 'Unknown', '182.177.151.55', NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Mobile Safari\\/537.36\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}'),
(9, 15, 'January 26,February 26', 'voucher_B23F0183CS098_1769293959.jpg', '2026-01-24 17:32:39', 'approved', '', '2026-01-25 02:05:55', 'Unknown', '39.48.17.24', NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Mobile Safari\\/537.36\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}'),
(10, 6, 'January 26,February 26', 'voucher_B23S1000AI032_1769304233.jpg', '2026-01-24 20:23:53', 'approved', '', '2026-01-25 02:06:11', 'Unknown', '39.62.169.6', NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Mobile Safari\\/537.36\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}'),
(11, 31, 'January 26', 'voucher_B25F0368CYS042_1769316687.jpg', '2026-01-24 23:51:27', 'approved', '', '2026-01-25 02:06:17', 'Unknown', '103.76.110.140', NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Mobile Safari\\/537.36\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}'),
(12, 2, 'January 26,February 26', 'voucher_B22F0834CS124_1769324768.jpg', '2026-01-25 02:06:08', 'approved', '', '2026-01-25 02:06:20', 'Unknown', '182.177.176.20', NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Mobile Safari\\/537.36\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}');

-- --------------------------------------------------------

--
-- Table structure for table `months`
--

CREATE TABLE `months` (
  `id` int(11) NOT NULL,
  `month_name` varchar(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `months`
--

INSERT INTO `months` (`id`, `month_name`) VALUES
(9, 'September'),
(10, 'October'),
(11, 'November'),
(12, 'December'),
(13, 'January 26'),
(14, 'February 26');

-- --------------------------------------------------------

--
-- Table structure for table `seats`
--

CREATE TABLE `seats` (
  `id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `is_booked` tinyint(1) DEFAULT 0,
  `passenger_name` varchar(100) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `booking_time` datetime DEFAULT NULL,
  `university_id` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `seats`
--

INSERT INTO `seats` (`id`, `seat_number`, `is_booked`, `passenger_name`, `gender`, `booking_time`, `university_id`) VALUES
(1, '1A', 1, 'Momin Khan', 'male', '2025-11-16 07:45:19', 'B22F1181AI056'),
(2, '1B', 1, 'Mashab Jadoon', 'male', '2025-11-16 10:08:25', 'B22F0834CS124'),
(3, '2A', 1, 'Omair Iftikhar', 'male', '2025-11-16 10:12:14', 'A-0126'),
(4, '2B', 1, 'Harbaz Khan Jadoon', 'male', '2025-11-16 10:13:25', 'B24F0080EE001'),
(5, '2C', 1, 'Saffiullah Ahmed Khan', 'male', '2025-11-16 10:13:07', 'B23S1000AI032'),
(6, '2D', 1, 'Sardar Zohaib Ahmed', 'male', '2025-11-16 10:12:37', 'B22F0220CS135'),
(7, '3A', 1, NULL, 'female', NULL, NULL),
(8, '3B', 1, NULL, 'female', NULL, NULL),
(9, '4A', 1, NULL, 'female', NULL, NULL),
(10, '4B', 1, NULL, 'female', NULL, NULL),
(11, '4C', 1, NULL, 'female', NULL, NULL),
(12, '4D', 1, NULL, 'female', NULL, NULL),
(13, '5A', 1, NULL, 'female', NULL, NULL),
(14, '5B', 1, NULL, 'female', NULL, NULL),
(15, '5C', 1, NULL, 'female', NULL, NULL),
(16, '5D', 1, NULL, 'female', NULL, NULL),
(17, '6A', 1, NULL, 'female', NULL, NULL),
(18, '6B', 1, NULL, 'female', NULL, NULL),
(19, '6C', 1, NULL, NULL, NULL, NULL),
(20, '6D', 1, NULL, NULL, NULL, NULL),
(21, '7A', 1, NULL, 'male', NULL, NULL),
(22, '7B', 1, NULL, 'male', NULL, NULL),
(23, '7C', 1, NULL, 'male', NULL, NULL),
(24, '7D', 1, NULL, 'male', NULL, NULL),
(25, '8A', 1, NULL, 'male', NULL, NULL),
(26, '8B', 1, NULL, 'male', NULL, NULL),
(27, '8C', 1, NULL, 'male', NULL, NULL),
(28, '8D', 1, NULL, 'male', NULL, NULL),
(29, '9A', 1, NULL, 'male', NULL, NULL),
(30, '9B', 1, NULL, 'male', NULL, NULL),
(31, '9C', 1, NULL, 'male', NULL, NULL),
(32, '9D', 1, NULL, 'male', NULL, NULL),
(33, '9E', 1, NULL, 'male', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `sno` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `university_id` varchar(50) NOT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `category` enum('Student','Faculty') DEFAULT 'Student',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `sno`, `name`, `university_id`, `semester`, `category`, `is_active`) VALUES
(1, 1, 'Momin Khan', 'B22F1181AI056', '7th', 'Student', 1),
(2, 2, 'Mashab Jadoon', 'B22F0834CS124', '7th', 'Student', 1),
(3, 3, 'Umama Jadoon', 'B24S0950AI005', '4th', 'Student', 1),
(4, 4, 'Sardar zohaib ahmed', 'B22F0220CS135', '7th', 'Student', 1),
(5, 5, 'Ruqiyya Eisha Zahid', 'B22F1011BMS035', '7th', 'Student', 1),
(6, 6, 'Saffiullah Ahmed Khan', 'B23S1000AI032', '6th', 'Student', 1),
(7, 7, 'Sardar Sayyam', 'B24F1642CS156', '2nd', 'Student', 1),
(8, 8, 'Harbaz Khan Jadoon', 'B24F0080EE001', '2nd', 'Student', 1),
(9, 9, 'Arsalan Ahmad Khan', 'B24F0425AI103', '2nd', 'Student', 1),
(10, 10, 'Sadeel Ahmed Khan', 'B24F0968AI091', '2nd', 'Student', 1),
(11, 11, 'Muhammad Hamza Zubair', 'B24F0426AI092', '3rd', 'Student', 1),
(12, 12, 'Ahsanullah Awan', 'B24F0139EE015', '2nd', 'Student', 1),
(13, 13, 'Rayaan Jadoon', 'B24F0204AI150', '2nd', 'Student', 1),
(14, 14, 'Abdul Mohiz', 'B23F0001DS007', '4th', 'Student', 1),
(15, 15, 'Ibrahim khan', 'B23F0183CS098', '5th', 'Student', 1),
(16, 16, 'Abdul waseh jadoon', 'B23f0001se019', '5th', 'Student', 1),
(17, 17, 'Syed Mehar Ali Shah', 'B23S0982DS008', '6th', 'Student', 1),
(18, 18, 'Mouattar', 'B23S0052AI001', '6th', 'Student', 1),
(20, 20, 'Amna Nisar', 'B24f0014fd005', '3rd', 'Student', 1),
(21, 21, 'Sardar Ahmed Ali', 'B24F1822AI203', '2nd', 'Student', 1),
(22, 22, 'Umair Dayyan', 'B23F0548AI100', '5th', 'Student', 1),
(23, 23, 'Aman', 'B24F0008CE007', '3rd', 'Student', 1),
(24, 24, 'Qazi Abdullah', 'B25F02900CS073', '1st', 'Student', 1),
(25, 25, 'Ishwa Quaid', 'B25F1797CS142', '1st', 'Student', 1),
(26, 26, 'Aila Jadoon', 'B25F2222CS143', '1st', 'Student', 1),
(27, 27, 'Uzair Ahmad', 'B25F0038CHE024', '1st', 'Student', 1),
(28, 28, 'Hadisa Sarfaraz', 'B25F0834PHY030', '1st', 'Student', 1),
(29, 29, 'Annusha Nadeem', 'B25F0193ADS002', '1st', 'Student', 1),
(30, 30, 'Haleema Hameed', 'B25F0246CYS154', '1st', 'Student', 1),
(31, 31, 'Rafia Qureshi', 'B25F0368CYS042', '1st', 'Student', 1),
(32, 32, 'Izza Iqbal', 'B23F0317AI119', '5th', 'Student', 1),
(33, 33, 'Hoor Ul Ain', 'B23F0023CS118', '5th', 'Student', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fee_payments`
--
ALTER TABLE `fee_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`month_id`),
  ADD KEY `month_id` (`month_id`);

--
-- Indexes for table `fee_vouchers`
--
ALTER TABLE `fee_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `months`
--
ALTER TABLE `months`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `month_name` (`month_name`);

--
-- Indexes for table `seats`
--
ALTER TABLE `seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `seat_number` (`seat_number`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `university_id` (`university_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `fee_payments`
--
ALTER TABLE `fee_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `fee_vouchers`
--
ALTER TABLE `fee_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `months`
--
ALTER TABLE `months`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `seats`
--
ALTER TABLE `seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

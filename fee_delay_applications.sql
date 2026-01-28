-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 28, 2026 at 01:36 AM
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
-- Table structure for table `fee_delay_applications`
--

CREATE TABLE `fee_delay_applications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `university_id` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `months_applied` varchar(255) NOT NULL,
  `reason_for_delay` text NOT NULL,
  `delay_period` varchar(50) NOT NULL,
  `requested_days` int(11) NOT NULL,
  `application_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','disapproved','forwarded_to_transport','under_review') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `forwarded_date` datetime DEFAULT NULL,
  `processed_date` datetime DEFAULT NULL,
  `admin_username` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fee_delay_applications`
--

INSERT INTO `fee_delay_applications` (`id`, `student_id`, `university_id`, `student_name`, `months_applied`, `reason_for_delay`, `delay_period`, `requested_days`, `application_date`, `status`, `admin_notes`, `forwarded_date`, `processed_date`, `admin_username`, `ip_address`, `device_info`) VALUES
(4, 4, 'B22F0220CS135', 'Sardar zohaib ahmed', 'September', 'Sona ha mujay ;(...', '7 days', 0, '2026-01-27 14:10:54', 'pending', NULL, NULL, NULL, NULL, '182.177.169.19', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"browser\":\"Unknown\",\"platform\":\"Unknown\"}');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `fee_delay_applications`
--
ALTER TABLE `fee_delay_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `fee_delay_applications`
--
ALTER TABLE `fee_delay_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `fee_delay_applications`
--
ALTER TABLE `fee_delay_applications`
  ADD CONSTRAINT `fk_fee_delay_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

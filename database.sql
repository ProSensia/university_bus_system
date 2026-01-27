-- ===============================
-- CREATE DATABASE
-- ===============================
CREATE DATABASE IF NOT EXISTS bus_booking;
USE bus_booking;

-- ===============================
-- DROP OLD TABLES
-- ===============================
DROP TABLE IF EXISTS fee_payments;
DROP TABLE IF EXISTS months;
DROP TABLE IF EXISTS students;

-- ===============================
-- TABLE 1: students
-- ===============================
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sno INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    university_id VARCHAR(50) NOT NULL UNIQUE,
    semester VARCHAR(20),
    category ENUM('Student', 'Faculty') DEFAULT 'Student',
    is_active BOOLEAN DEFAULT TRUE
);

-- ===============================
-- INSERT STUDENT DATA (CLEANED)
-- ===============================
INSERT INTO students (sno, name, university_id, semester, category) VALUES
(1, 'Momin Khan', 'B22F1181AI056', '7', 'Student'),
(2, 'Umama Jadoon', 'B24S0950AI005', '4', 'Student'),
(3, 'Sardar Zohaib Ahmed', 'B22F0220CS135', '7', 'Student'),
(4, 'Ruqiyya Eisha Zahid', 'B22F1011BMS035', '7', 'Student'),
(5, 'Saffiullah Ahmed Khan', 'B23S1000AI032', '6', 'Student'),
(6, 'Mashab Jadoon', 'AB22F0834CS124', '7', 'Student'),
(7, 'Sardar Sayyam', 'B24F1642CS156', '2', 'Student'),
(8, 'Harbaz Khan Jadoon', 'B24F0080EE001', '2', 'Student'),
(9, 'Arsalan Ahmad Khan', 'B24F0425AI103', '2', 'Student'),
(10, 'Sadeel Ahmed Khan', 'B24F0968AI091', '2', 'Student'),
(11, 'Muhammad Hamza Zubair', 'B24F0426AI092', '3', 'Student'),
(12, 'Ahsanullah Avan', 'B24F0139EE015', '2', 'Student'),
(13, 'Rayaan Jadoon', 'B24F0204AI150', '2', 'Student'),
(14, 'Ahmed Ali', 'B24F0067RO053', '2', 'Student'),
(15, 'Sayyam Khan', 'B24F0134RO054', '2', 'Student'),
(16, 'Ibrahim Khan', 'B23F0183CS098', '5', 'Student'),
(17, 'Abdul Waseh Jadoon', 'B23F0001SE019', '5', 'Student'),
(18, 'Syed Mehar Ali Shah', 'B23S0982DS008', '6', 'Student'),
(19, 'Mouattar', 'B23S0052AI001', '6', 'Student'),
(20, 'Umair Dayyan', 'B23F0548AI100', '5', 'Student'),
(21, 'Anna Nisar', 'B24F0014FA005', '3', 'Student'),
(22, 'Sardar Ahmed Ali', 'B24F1822AI203', '2', 'Student'),
(23, 'Aman', 'B24F0008CE007', '3', 'Student'),
(24, 'Qazi Abdullah', 'B25F02900CS073', '1', 'Student'),
(25, 'Ishwa Quaid', 'B25F1797CS142', '1', 'Student'),
(26, 'Alla Jadoon', 'B25F2222CS143', '1', 'Student'),
(27, 'Uzair Ahmad', 'B25F0038CHE024', '1', 'Student'),
(28, 'Hadisa Sarfaraz', 'B25F0834PHY030', '1', 'Student'),
(29, 'Amusha Nadeem', 'B25F0193ADS002', '1', 'Student'),

-- Faculty (no fee)
(30, 'Omair Iftikhar', 'A-0126', NULL, 'Faculty'),
(31, 'Sara Rafaq', 'FACULTY-MEMBER', NULL, 'Faculty');

-- ===============================
-- TABLE 2: months
-- ===============================
CREATE TABLE months (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month_name VARCHAR(20) UNIQUE NOT NULL
);

INSERT INTO months (month_name) VALUES
('September'),
('October'),
('November'),
('December');

-- ===============================
-- TABLE 3: fee_payments
-- ===============================
CREATE TABLE fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    month_id INT NOT NULL,
    status ENUM('Submitted', 'Pending') DEFAULT 'Pending',
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (month_id) REFERENCES months(id),
    UNIQUE (student_id, month_id)
);

-- ===============================
-- FEE CATEGORY MAPPING
-- ===============================

-- 1️⃣ FULLY PAID (ALL MONTHS)
INSERT INTO fee_payments (student_id, month_id, status)
SELECT s.id, m.id, 'Submitted'
FROM students s
JOIN months m
WHERE s.university_id IN (
    'B22F1181AI056',
    'B24S0950AI005',
    'B22F0220CS135',
    'B22F1011BMS035',
    'B23S1000AI032',
    'AB22F0834CS124',
    'B24F1642CS156',
    'B24F0080EE001',
    'B23S0052AI001',
    'B24F0014FA005',
    'B24F0008CE007'
);

-- 2️⃣ PAID ONLY OCT, NOV, DEC (SEP = Pending)
INSERT INTO fee_payments (student_id, month_id, status)
SELECT s.id, m.id,
CASE WHEN m.month_name='September' THEN 'Pending' ELSE 'Submitted' END
FROM students s
JOIN months m
WHERE s.university_id IN (
    'B24F0425AI103',
    'B24F0968AI091',
    'B24F0426AI092',
    'B24F0139EE015',
    'B24F0204AI150',
    'B24F0067RO053',
    'B24F0134RO054',
    'B23F0183CS098',
    'B23F0001SE019',
    'B23S0982DS008',
    'B23F0548AI100',
    'B24F1822AI203',
    'B25F02900CS073',
    'B25F1797CS142',
    'B25F2222CS143',
    'B25F0038CHE024',
    'B25F0834PHY030',
    'B25F0193ADS002'
);

-- 3️⃣ FACULTY → NO PAYMENTS (All Pending)
INSERT INTO fee_payments (student_id, month_id, status)
SELECT s.id, m.id, 'Pending'
FROM students s
JOIN months m
WHERE s.category = 'Faculty';
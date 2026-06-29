-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2026 at 06:45 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `adms`
--

-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--

CREATE TABLE `alumni` (
  `id` int(11) NOT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `academic_degree` varchar(50) DEFAULT NULL,
  `college` varchar(100) DEFAULT NULL,
  `major` varchar(100) DEFAULT NULL,
  `study_type` varchar(50) DEFAULT NULL,
  `graduation_term` varchar(20) DEFAULT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `academic_grade` varchar(50) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `honor_rank` varchar(100) DEFAULT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alumni`
--

INSERT INTO `alumni` (`id`, `campus`, `gender`, `academic_degree`, `college`, `major`, `study_type`, `graduation_term`, `student_id`, `name`, `national_id`, `nationality`, `gpa`, `academic_grade`, `mobile`, `email`, `honor_rank`, `profile_photo`, `bio`, `created_at`) VALUES
(1, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '261', '400000001', 'طالب ديمو رقم 1', '0000000001', 'سعودي', 3.53, 'جيد', NULL, NULL, NULL, NULL, NULL, '2026-05-15 20:05:33'),
(2, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '262', '400000002', ' طالب ديمو رقم 2', '0000000002', 'سعودي', 3.15, 'جيد', NULL, NULL, NULL, NULL, NULL, '2026-05-15 20:05:33'),
(3, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '251', '400000003', ' طالب ديمو رقم 3', '0000000003', 'سعودي', 3.75, 'ممتاز', NULL, NULL, NULL, NULL, NULL, '2026-05-15 20:05:33'),
(4, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '231', '400000004', ' طالب ديمو رقم 4', '0000000004', 'سعودي', 3.90, 'ممتاز', NULL, NULL, 'مرتبة الشرف الثانية', NULL, NULL, '2026-05-15 20:05:33'),
(5, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '262', '400000005', ' طالب ديمو رقم 5', '0000000005', 'سعودي', 3.80, 'ممتاز', NULL, NULL, 'مرتبة الشرف الثانية', NULL, NULL, '2026-05-15 20:05:33'),
(6, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '261', '400000006', ' طالب ديمو رقم 6', '0000000006', 'سعودي', 3.65, 'جيد', NULL, NULL, NULL, NULL, NULL, '2026-05-15 20:05:33'),
(7, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '261', '400000007', ' طالب ديمو رقم 7', '0000000007', 'سعودي', 3.70, 'جيد', NULL, NULL, NULL, NULL, NULL, '2026-05-15 20:05:33'),
(8, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '251', '400000008', ' طالب ديمو رقم 8', '0000000008', 'سعودي', 4.25, 'ممتاز', NULL, NULL, 'مرتبة الشرف الثانية', NULL, NULL, '2026-05-15 20:05:33'),
(9, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '252', '400000009', ' طالب ديمو رقم 9', '0000000009', 'سعودي', 4.64, 'ممتاز', NULL, NULL, 'مرتبة الشرف الأولى', NULL, NULL, '2026-05-15 20:05:33'),
(10, 'المقر الرئيس طلاب', 'ذكر', 'بكالوريوس', 'الحاسب', 'علوم الحاسب', 'انتظام', '252', '400000010', ' طالب ديمو رقم 10', '0000000010', 'سعودي', 4.75, 'ممتاز', NULL, NULL, 'مرتبة الشرف الأولى', NULL, NULL, '2026-05-15 20:05:33'),

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alumni`
--
ALTER TABLE `alumni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alumni`
--
ALTER TABLE `alumni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3140;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

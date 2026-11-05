-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 28, 2026 at 07:50 AM
-- Server version: 8.0.46-0ubuntu0.24.04.4
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `webspaceit_oop_exam`
--

-- --------------------------------------------------------

--
-- Table structure for table `codex_schema_migrations`
--

DROP TABLE IF EXISTS `codex_schema_migrations`;
CREATE TABLE `codex_schema_migrations` (
  `migration` varchar(190) NOT NULL,
  `applied_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `codex_schema_migrations`
--

INSERT INTO `codex_schema_migrations` (`migration`, `applied_at`) VALUES
('2026_10_23_user_login_history', '2026-10-23 18:38:44'),
('2026_10_24_center_display_name', '2026-10-24 13:13:03'),
('2026_10_24_students_session_created_index', '2026-10-23 19:26:01'),
('2026_10_25_student_photo_sync_history', '2026-10-25 11:02:04'),
('2026_10_26_center_reference_sync', '2026-10-25 18:39:41'),
('2026_10_26_location_reference_sync', '2026-10-25 18:54:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `codex_schema_migrations`
--
ALTER TABLE `codex_schema_migrations`
  ADD PRIMARY KEY (`migration`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

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
-- Table structure for table `codex_student_photo_sync_runs`
--

DROP TABLE IF EXISTS `codex_student_photo_sync_runs`;
CREATE TABLE `codex_student_photo_sync_runs` (
  `id` bigint UNSIGNED NOT NULL,
  `run_id` char(36) NOT NULL,
  `session` int NOT NULL,
  `status` varchar(20) NOT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  `last_source_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `checked_count` bigint UNSIGNED NOT NULL DEFAULT '0',
  `copied_count` bigint UNSIGNED NOT NULL DEFAULT '0',
  `missing_demo_count` bigint UNSIGNED NOT NULL DEFAULT '0',
  `unavailable_count` bigint UNSIGNED NOT NULL DEFAULT '0',
  `failure_message` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `codex_student_photo_sync_runs`
--

INSERT INTO `codex_student_photo_sync_runs` (`id`, `run_id`, `session`, `status`, `started_at`, `finished_at`, `updated_at`, `last_source_id`, `checked_count`, `copied_count`, `missing_demo_count`, `unavailable_count`, `failure_message`) VALUES
(1, '0c4427b2-ccca-4846-a499-08e0ceb10fcd', 2026, 'completed', '2026-10-25 11:03:46', '2026-10-25 19:24:29', '2026-10-25 19:24:29', 171130, 58352, 34527, 1, 424, NULL),
(2, 'c7b85240-de8e-4aeb-bd1d-b52e26695cb9', 2026, 'completed', '2026-10-25 12:04:42', '2026-10-25 19:15:11', '2026-10-25 19:15:11', 171130, 58352, 29315, 1, 456, NULL),
(3, '9a085fc8-e7f9-404c-a6ad-d5d9e3c04fef', 2026, 'completed', '2026-10-25 13:15:22', '2026-10-25 13:58:26', '2026-10-25 13:58:26', 171112, 58334, 23269, 44, 401, NULL),
(4, '80ae4fa8-42f5-402f-b62b-d8ac66010529', 2026, 'completed', '2026-10-25 14:41:41', '2026-10-25 15:58:54', '2026-10-25 15:58:54', 171115, 58337, 113, 3, 332, NULL),
(5, 'f7de599c-5037-40cb-a0fa-c638eb1c1648', 2026, 'completed', '2026-10-25 20:04:47', '2026-10-26 04:26:30', '2026-10-26 04:26:30', 171137, 58359, 0, 8, 331, NULL),
(6, '3e21073e-692d-4773-9eab-2785c71e063b', 2026, 'completed', '2026-10-26 05:49:02', '2026-10-26 14:41:10', '2026-10-26 14:41:10', 171150, 58372, 23, 1, 327, NULL),
(7, '150a304f-c7a4-4a5d-aecb-64d49a0f686a', 2026, 'completed', '2026-10-26 05:49:08', '2026-10-26 06:19:22', '2026-10-26 06:19:22', 171142, 58364, 17, 1, 328, NULL),
(8, '43ec7f1f-c49d-4caf-8b90-06f807502903', 2026, 'completed', '2026-10-26 16:36:40', '2026-10-26 17:02:08', '2026-10-26 17:02:08', 171158, 58380, 7, 11, 326, NULL),
(9, '7030f02f-1231-42d9-9d8c-0601e0959dd6', 2026, 'completed', '2026-10-27 00:39:12', '2026-10-27 00:48:34', '2026-10-27 00:48:34', 171207, 58428, 72, 1, 320, NULL),
(10, '6e9687c9-1ee2-4e8d-a8ac-b5aa84e614be', 2026, 'completed', '2026-10-27 00:57:17', '2026-10-27 15:41:52', '2026-10-27 15:41:52', 171211, 58432, 4, 1, 320, NULL),
(11, 'ede8d0fc-c81f-47f1-abd7-33daff2fe205', 2026, 'completed', '2026-10-27 17:41:09', '2026-10-27 17:56:25', '2026-10-27 17:56:25', 171217, 58438, 12, 1, 316, NULL),
(12, '49bcecb7-7365-40e0-87ff-d15c232248df', 2026, 'completed', '2026-10-27 23:53:41', '2026-10-28 00:04:22', '2026-10-28 00:04:22', 171247, 58468, 54, 3, 301, NULL),
(13, 'ebc3b564-1917-47a8-8fd8-5e7d6b076a46', 2026, 'completed', '2026-10-28 10:10:20', '2026-10-28 10:25:49', '2026-10-28 10:25:49', 171257, 58478, 24, 6, 299, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `codex_student_photo_sync_runs`
--
ALTER TABLE `codex_student_photo_sync_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_codex_photo_sync_run_id` (`run_id`),
  ADD KEY `idx_codex_photo_sync_started_at` (`started_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `codex_student_photo_sync_runs`
--
ALTER TABLE `codex_student_photo_sync_runs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

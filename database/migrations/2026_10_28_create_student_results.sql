-- Phase 2: Create student_results table and backfill
-- Additive only. Keep students.* result columns for now.

CREATE TABLE IF NOT EXISTS `student_results` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `session` INT DEFAULT NULL,
  `class` TINYINT DEFAULT NULL,
  `bangla` INT NOT NULL DEFAULT 0,
  `english` INT NOT NULL DEFAULT 0,
  `math` INT NOT NULL DEFAULT 0,
  `total` INT NOT NULL DEFAULT 0,
  `percentage` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `grade` TINYINT DEFAULT 0,
  `ml` INT NOT NULL DEFAULT 0,
  `place_id` BIGINT UNSIGNED DEFAULT NULL,
  `marker_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_results_student_id` (`student_id`),
  KEY `idx_student_results_session_class` (`session`,`class`),
  CONSTRAINT `fk_student_results_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill from students where marks/placement exist or non-default
INSERT IGNORE INTO `student_results` 
  (`student_id`, `session`, `class`, `bangla`, `english`, `math`, `total`, `percentage`, `grade`, `ml`, `place_id`, `marker_id`, `created_at`, `updated_at`)
SELECT 
  `id`, `session`, `class`, `bangla`, `english`, `math`, `total`, `percentage`, `grade`, `ml`, `place_id`, `marker_id`, `created_at`, `updated_at`
FROM `students`
WHERE (`bangla` != 0 OR `english` != 0 OR `math` != 0 OR `total` != 0 OR `place_id` IS NOT NULL OR `marker_id` IS NOT NULL);

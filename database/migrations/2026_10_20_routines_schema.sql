-- Safe routine schema upgrade for existing demo installations.
-- Creates the table if absent and adds the session field when needed.
CREATE TABLE IF NOT EXISTS `routines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session` int DEFAULT NULL,
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` int DEFAULT NULL,
  `active` int NOT NULL DEFAULT '1',
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @routine_session_column_exists := (
  SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
   WHERE `TABLE_SCHEMA` = DATABASE()
     AND `TABLE_NAME` = 'routines'
     AND `COLUMN_NAME` = 'session'
);
SET @routine_session_migration := IF(
  @routine_session_column_exists = 0,
  'ALTER TABLE `routines` ADD COLUMN `session` int DEFAULT NULL AFTER `time`',
  'SELECT 1'
);
PREPARE routine_session_stmt FROM @routine_session_migration;
EXECUTE routine_session_stmt;
DEALLOCATE PREPARE routine_session_stmt;

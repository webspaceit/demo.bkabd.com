-- ============================================================
-- Upgrade notes — SQL to apply on existing databases
-- (new installs get these via database/schema.sql)
-- ============================================================

-- ------------------------------------------------------------
-- Seat Plan room assignments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `seat_plan_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session` int NOT NULL,
  `center_id` int NOT NULL,
  `class` tinyint NOT NULL,
  `building_name` varchar(120) NOT NULL,
  `floor_number` varchar(50) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `roll_start` int DEFAULT NULL,
  `roll_end` int DEFAULT NULL,
  `room_capacity` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_seat_plan_scope` (`session`,`center_id`,`class`,`sort_order`),
  KEY `idx_seat_plan_rolls` (`session`,`center_id`,`class`,`roll_start`,`roll_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Exam routines — scope schedules to an exam session.
-- Class was already present in routines but was not exposed in the UI.
-- Existing routines remain unscoped (NULL) until assigned in Edit Routine.
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- Public result portal — performance indexes for result day
-- (mirrors the KEY lines added to database/schema.sql)
-- ------------------------------------------------------------
ALTER TABLE `students` ADD INDEX `idx_session_center_class` (`session`, `center_id`, `class`);
ALTER TABLE `students` ADD INDEX `idx_session_class_roll` (`session`, `class`, `roll`);
ALTER TABLE `sessions` ADD INDEX `idx_result` (`result`);

-- ------------------------------------------------------------
-- Institutes module — validity fields
-- (the original Laravel migration never ran on the source DB, so the
--  original updateValidity endpoint 500'd with "Unknown column
--  'last_renewal_date'" — see storage/logs/laravel.log in the original
--  app. Mirrors the columns added to the institutes table in
--  database/schema.sql; required by PUT /api/institutevalidity/{id}.)
-- ------------------------------------------------------------
ALTER TABLE `institutes`
  ADD COLUMN `validity_date` date DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `last_renewal_date` date DEFAULT NULL AFTER `validity_date`,
  ADD COLUMN `validity_user_id` int DEFAULT NULL AFTER `last_renewal_date`;

-- ---------------------------------------------------------------
-- Super Admin role must hold EVERY permission. The source DB dump only
-- gave role 5 permission ids 1-73, so /sessions (session-list=79),
-- /role-place (74), /mark-distributions (75) and institute-validity (83)
-- returned 403 even for admin@bkabd.com. Grant everything missing.
-- (database/grant_admin_all.sql contains just this statement.)
-- ---------------------------------------------------------------
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, 5 FROM `permissions` p
WHERE NOT EXISTS (
  SELECT 1 FROM `role_has_permissions` rp
  WHERE rp.`permission_id` = p.`id` AND rp.`role_id` = 5
);

-- ---------------------------------------------------------------
-- Security hardening — two-factor authentication (TOTP)
-- New installs get the columns from database/schema.sql; this mirrors
-- them for existing databases. Users.two_factor_enabled + secret are
-- managed via /api/mfa/setup|enable|disable (auth) and the two-step
-- login at POST /api/login + POST /api/login/mfa.
-- ---------------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN `two_factor_secret` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0';

-- ---------------------------------------------------------------
-- Unions — enable the Union cascade on the registration form.
-- The original schema made `name` globally UNIQUE and left `id`
-- without AUTO_INCREMENT, which blocks importing real union data
-- (the same union name recurs in many upazilas). Relax to a
-- per-upazila unique and let the id auto-increment. New installs get
-- this from database/schema.sql. Safe on an empty or already-unique
-- table. Import the rows with: php tools/import_unions.php <file>
-- ---------------------------------------------------------------
ALTER TABLE `unions` DROP INDEX `unions_name_unique`;
ALTER TABLE `unions` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `unions` ADD UNIQUE KEY `unions_upozila_name_unique` (`upozila_id`, `name`);
-- ---------------------------------------------------------------
-- Manage Area > Union Info — union-* permissions.
-- Mirrors each union-<action> onto the roles that already hold the
-- matching upazila-<action>, and always grants Super Admin (role 5).
-- Idempotent: safe to run on a fresh or already-migrated database.
-- ---------------------------------------------------------------
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT v.`name`, 'sanctum', NOW(), NOW() FROM (
    SELECT 'union-list' AS `name`
    UNION ALL SELECT 'union-create'
    UNION ALL SELECT 'union-edit'
    UNION ALL SELECT 'union-delete'
) v
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`name` = v.`name`);

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT un.`id`, rp.`role_id`
FROM `permissions` un
JOIN `permissions` uz ON uz.`name` = CONCAT('upazila-', SUBSTRING_INDEX(un.`name`, '-', -1))
JOIN `role_has_permissions` rp ON rp.`permission_id` = uz.`id`
WHERE un.`name` LIKE 'union-%'
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` x
      WHERE x.`permission_id` = un.`id` AND x.`role_id` = rp.`role_id`
  );

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, 5 FROM `permissions` p
WHERE p.`name` LIKE 'union-%'
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` x
      WHERE x.`permission_id` = p.`id` AND x.`role_id` = 5
  );

-- ---------------------------------------------------------------
-- Students > Pending Registration — dedicated `student-approve`.
-- Approving (or rejecting) a pending registration is a privileged action,
-- so it no longer rides on `student-edit`. Grant it to every role that
-- already holds `student-edit` — no behaviour change for existing roles —
-- and always to Super Admin (5). Roles can then be split (e.g. edit a
-- student without being allowed to approve registrations) from the
-- Manage User > Role permission matrix.
-- Idempotent: safe to run on a fresh or already-migrated database.
-- ---------------------------------------------------------------
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'student-approve', 'sanctum', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`name` = 'student-approve');

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT sa.`id`, rp.`role_id`
FROM `permissions` sa
JOIN `permissions` se ON se.`name` = 'student-edit'
JOIN `role_has_permissions` rp ON rp.`permission_id` = se.`id`
WHERE sa.`name` = 'student-approve'
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` x
      WHERE x.`permission_id` = sa.`id` AND x.`role_id` = rp.`role_id`
  );

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, 5 FROM `permissions` p
WHERE p.`name` = 'student-approve'
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` x
      WHERE x.`permission_id` = p.`id` AND x.`role_id` = 5
  );

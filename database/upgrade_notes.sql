-- ============================================================
-- Upgrade notes — SQL to apply on existing databases
-- (new installs get these via database/schema.sql)
-- ============================================================

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

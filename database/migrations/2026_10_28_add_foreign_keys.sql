-- Phase 1: Add foreign key constraints (guarded, additive only)
-- Handles type compatibility between INT and BIGINT UNSIGNED columns.
-- Safe to re-run.

-- Ensure column types match for FK compatibility
-- students.center_id: int -> bigint unsigned (centers.id is bigint unsigned)
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='center_id' AND COLUMN_TYPE='int') > 0,
  'ALTER TABLE `students` MODIFY `center_id` BIGINT UNSIGNED NOT NULL',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='institute_id' AND COLUMN_TYPE='int') > 0,
  'ALTER TABLE `students` MODIFY `institute_id` BIGINT UNSIGNED NOT NULL',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='division_id' AND COLUMN_TYPE='int') > 0,
  'ALTER TABLE `students` MODIFY `division_id` BIGINT UNSIGNED NOT NULL',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='district_id' AND COLUMN_TYPE='int') > 0,
  'ALTER TABLE `students` MODIFY `district_id` BIGINT UNSIGNED NOT NULL',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='upazila_id' AND COLUMN_TYPE='int') > 0,
  'ALTER TABLE `students` MODIFY `upazila_id` BIGINT UNSIGNED NOT NULL',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='union_id' AND COLUMN_TYPE='int') > 0,
  'ALTER TABLE `students` MODIFY `union_id` BIGINT UNSIGNED NULL',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- institutes.center_id
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institutes' AND COLUMN_NAME='center_id' AND COLUMN_TYPE='int') > 0,
  'ALTER TABLE `institutes` MODIFY `center_id` BIGINT UNSIGNED NULL',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- centers.user_id, districts.user_id etc might need checking but let's add FKs
-- Add foreign keys if they don't exist
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME='fk_students_center' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `students` ADD CONSTRAINT `fk_students_center` FOREIGN KEY (`center_id`) REFERENCES `centers`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME='fk_students_institute' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `students` ADD CONSTRAINT `fk_students_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME='fk_students_division' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `students` ADD CONSTRAINT `fk_students_division` FOREIGN KEY (`division_id`) REFERENCES `divisions`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME='fk_students_district' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `students` ADD CONSTRAINT `fk_students_district` FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME='fk_students_upazila' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `students` ADD CONSTRAINT `fk_students_upazila` FOREIGN KEY (`upazila_id`) REFERENCES `upozilas`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME='fk_students_union' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `students` ADD CONSTRAINT `fk_students_union` FOREIGN KEY (`union_id`) REFERENCES `unions`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institutes' AND CONSTRAINT_NAME='fk_institutes_center' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `institutes` ADD CONSTRAINT `fk_institutes_center` FOREIGN KEY (`center_id`) REFERENCES `centers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='districts' AND CONSTRAINT_NAME='fk_districts_division' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `districts` ADD CONSTRAINT `fk_districts_division` FOREIGN KEY (`division_id`) REFERENCES `divisions`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='upozilas' AND CONSTRAINT_NAME='fk_upozilas_district' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `upozilas` ADD CONSTRAINT `fk_upozilas_district` FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unions' AND CONSTRAINT_NAME='fk_unions_upozila' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `unions` ADD CONSTRAINT `fk_unions_upozila` FOREIGN KEY (`upozila_id`) REFERENCES `upozilas`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Pivot tables
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_has_permissions' AND CONSTRAINT_NAME='fk_rhp_permission' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `role_has_permissions` ADD CONSTRAINT `fk_rhp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_has_permissions' AND CONSTRAINT_NAME='fk_rhp_role' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `role_has_permissions` ADD CONSTRAINT `fk_rhp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='model_has_roles' AND CONSTRAINT_NAME='fk_mhr_role' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `model_has_roles` ADD CONSTRAINT `fk_mhr_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='model_has_permissions' AND CONSTRAINT_NAME='fk_mhp_permission' AND CONSTRAINT_TYPE='FOREIGN KEY') = 0,
  'ALTER TABLE `model_has_permissions` ADD CONSTRAINT `fk_mhp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1'
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

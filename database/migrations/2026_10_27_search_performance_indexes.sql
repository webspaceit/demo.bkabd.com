-- Search/list performance: the students, institutes and centers tables were
-- created without the indexes their list pages need, so every page of the
-- student list did a 169k-row scan + filesort and the institute filter
-- dropdown sorted all 3.3k institutes on every request.
--
-- Every statement is guarded, so this file is safe to re-run.
-- Format note: one statement per line (tools/dbq.php splits on ";\n").

-- students: default list order (created_at DESC, id DESC).
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_students_created_id` ON `students` (`created_at`, `id`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_created_id');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- students: per-center scoped lists and the center row count.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_students_center_created` ON `students` (`center_id`, `created_at`, `id`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_center_created');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- students: per-institute scoped lists and the institute row count.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_students_institute_created` ON `students` (`institute_id`, `created_at`, `id`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_institute_created');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- students: status filter (pending queue = active 0/NULL) inside a session.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_students_session_active` ON `students` (`session`, `active`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_session_active');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- students: roll lookups (list filter, duplicate guard's `roll = ?` probe).
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_students_roll` ON `students` (`roll`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_roll');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- students: name lookups and ordering.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_students_name` ON `students` (`name`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_name');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- institutes: active dropdown ordered by name.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_institutes_active_name` ON `institutes` (`active`, `name`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institutes' AND INDEX_NAME = 'idx_institutes_active_name');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- institutes: per-center active list ordered by name.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_institutes_center_active_name` ON `institutes` (`center_id`, `active`, `name`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institutes' AND INDEX_NAME = 'idx_institutes_center_active_name');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- institutes: name lookups.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_institutes_name` ON `institutes` (`name`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institutes' AND INDEX_NAME = 'idx_institutes_name');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- centers: the list joins users per row.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_centers_user_id` ON `centers` (`user_id`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'centers' AND INDEX_NAME = 'idx_centers_user_id');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- centers: code search.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_centers_code` ON `centers` (`code`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'centers' AND INDEX_NAME = 'idx_centers_code');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- centers: approved/pending filter.
SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX `idx_centers_active` ON `centers` (`active`)', 'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'centers' AND INDEX_NAME = 'idx_centers_active');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

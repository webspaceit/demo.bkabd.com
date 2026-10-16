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

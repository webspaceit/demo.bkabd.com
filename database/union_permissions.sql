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

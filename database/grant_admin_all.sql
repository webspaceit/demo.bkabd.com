-- Grant the Super Admin role (5) every permission that exists.
-- The source DB dump only gave role 5 permissions 1-73, so pages like
-- /sessions (session-list=79), /role-place (74), /mark-distributions (75)
-- returned 403 even for the Super Admin. A Super Admin must see every
-- sidebar item. Safe to run repeatedly (NOT EXISTS guard).
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, 5 FROM `permissions` p
WHERE NOT EXISTS (
  SELECT 1 FROM `role_has_permissions` rp
  WHERE rp.`permission_id` = p.`id` AND rp.`role_id` = 5
);

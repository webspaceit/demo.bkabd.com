-- =============================================================
-- Demo data for oop_exam (safe to skip in production import).
-- Credentials:
--   admin@bkabd.com  / admin123   (admin, type=1, role "Super Admin")
--   center@bkabd.com / center123  (center user, type=4, role "Center")
-- =============================================================

-- Demo users -----------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `type`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin',  'admin@bkabd.com',  '01700000000', '$2y$10$tvDD/OvMfwg7y9hJf6gwNO2uHEXv8xvGzylob7yoFh.MdTLKQ0xbm', 1, NOW(), NOW()),
(2, 'Center User',  'center@bkabd.com', '01700000001', '$2y$10$ZyEQMT6sMbpYFPwwXouay.fvSGMq4Wx9H3uuVxHZlPob6yYQG/tte', 4, NOW(), NOW());

-- RBAC assignments (roles 5=Super Admin, 4=Center from seed) ----
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(5, 'App\\Models\\User', 1),
(4, 'App\\Models\\User', 2);

-- Super Admin (role 5) must hold EVERY permission — the source dump only
-- gave it ids 1-73, which made /sessions, /role-place, /mark-distributions
-- return 403 for admin. Same guard as database/grant_admin_all.sql.
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, 5 FROM `permissions` p
WHERE NOT EXISTS (
  SELECT 1 FROM `role_has_permissions` rp
  WHERE rp.`permission_id` = p.`id` AND rp.`role_id` = 5
);

-- Demo center (linked to user 2) --------------------------------
INSERT INTO `centers` (`id`, `user_id`, `website`, `division_id`, `district_id`, `upazila_id`, `union_id`, `authority_person`, `institute_name`, `code`, `active`, `note`, `created_at`, `updated_at`) VALUES
(1, 2, 'https://demo.bkabd.test', 6, 47, 1, NULL, 'Demo Authority', 'Demo Exam Center', 'C001', 1, 'Demo center', NOW(), NOW());

-- Demo institute -------------------------------------------------
INSERT INTO `institutes` (`id`, `center_id`, `name`, `phone`, `email`, `division_id`, `district_id`, `upazila_id`, `director_name`, `address`, `zip`, `country`, `type`, `recognized`, `membership_type`, `member_type`, `certificate_no`, `total_students`, `total_teachers`, `paid`, `active`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'Demo Model Kindergarten', '01700000002', 'institute@demo.test', 6, 47, 1, 'Demo Director', 'House 1, Road 1, Dhaka', '1207', 'Bangladesh', 1, 1, 1, 1, 'CERT-0001', 6, 4, 1, 1, 2, NOW(), NOW());

-- 2026 full marks config + open result portal for demo ----------
INSERT INTO `marks` (`bangla`, `english`, `math`, `total`, `user_id`, `session`, `created_at`, `updated_at`) VALUES
(30, 30, 40, 100, 1, 2026, NOW(), NOW());

UPDATE `sessions` SET `result` = 1, `result_time` = '2020-01-01 00:00:00' WHERE `session` = 2026;

-- Demo students (session 2026, center 1, institute 1) -----------
-- Class 1: rolls 10001-10003, Class 2: rolls 20001-20003
INSERT INTO `students`
(`name`,`father_name`,`mother_name`,`roll`,`registration`,`phone`,`dob`,`religion`,`gender`,`permanent_address`,`present_address`,`class`,`session`,`education_type`,`principal_name`,`center_id`,`institute_id`,`division_id`,`district_id`,`upazila_id`,`photo`,`paid`,`active`,`user_id`,`bangla`,`english`,`math`,`total`,`percentage`,`grade`,`ml`,`created_at`,`updated_at`) VALUES
('Rahim Uddin',   'Karim Uddin',   'Ayesha Begum', 10001, '2600100011001', '01711111111', '2015-03-11', '1', '1', 'Dhaka', 'Dhaka', 1, 2026, '1', 'Demo Director', 1, 1, 6, 47, 1, NULL, 1, 1, 2, 28, 27, 38,  93, 93.00, 2, 0, NOW(), NOW()),
('Karima Akter',  'Abdul Karim',   'Selina Akter',  10002, '2600100011002', '01711111112', '2015-07-02', '1', '2', 'Dhaka', 'Dhaka', 1, 2026, '1', 'Demo Director', 1, 1, 6, 47, 1, NULL, 1, 1, 2, 30, 30, 40, 100, 100.00, 1, 1, NOW(), NOW()),
('Sohel Rana',    'Mizan Rana',    'Nasima Khatun',10003, '2600100011003', '01711111113', '2014-12-20', '1', '1', 'Dhaka', 'Dhaka', 1, 2026, '1', 'Demo Director', 1, 1, 6, 47, 1, NULL, 1, 1, 2, 22, 20, 30,  72, 72.00, 3, 0, NOW(), NOW()),
('Jahangir Alam', 'Moslem Alam',   'Rahima Begum', 20001, '2600100012001', '01711111114', '2014-01-15', '1', '1', 'Dhaka', 'Dhaka', 2, 2026, '1', 'Demo Director', 1, 1, 6, 47, 1, NULL, 1, 1, 2, 25, 24, 33,  82, 82.00, 2, 0, NOW(), NOW()),
('Fatema Begum',  'Abdus Salam',   'Momena Begum', 20002, '2600100012002', '01711111115', '2014-05-30', '1', '2', 'Dhaka', 'Dhaka', 2, 2026, '1', 'Demo Director', 1, 1, 6, 47, 1, NULL, 1, 1, 2, 29, 28, 36,  93, 93.00, 2, 0, NOW(), NOW()),
('Arif Hasan',    'Shafiqul Islam','Rehana Begum', 20003, '2600100012003', '01711111116', '2014-09-09', '1', '1', 'Dhaka', 'Dhaka', 2, 2026, '1', 'Demo Director', 1, 1, 6, 47, 1, NULL, 1, 1, 2, 30, 30, 40, 100, 100.00, 1, 1, NOW(), NOW());

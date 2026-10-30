<?php

return [
    "ALTER TABLE `centers` ADD COLUMN IF NOT EXISTS `contact_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `display_name`",
    "ALTER TABLE `centers` ADD COLUMN IF NOT EXISTS `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `contact_phone`",
];

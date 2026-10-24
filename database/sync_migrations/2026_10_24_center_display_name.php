<?php

return [
    "ALTER TABLE `centers` ADD COLUMN IF NOT EXISTS `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `code`",
];

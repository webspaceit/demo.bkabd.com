<?php

return [
    "CREATE TABLE IF NOT EXISTS `user_login_history` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `user_id` bigint unsigned NOT NULL, `user_name` varchar(255) NOT NULL, `user_email` varchar(255) NOT NULL, `ip_address` varchar(45) DEFAULT NULL, `user_agent` varchar(500) DEFAULT NULL, `logged_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `user_login_history_user_id_index` (`user_id`), KEY `user_login_history_logged_at_index` (`logged_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

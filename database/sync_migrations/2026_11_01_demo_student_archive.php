<?php

return [
    "CREATE TABLE IF NOT EXISTS `codex_archived_students` (
        `student_id` bigint unsigned NOT NULL,
        `session` int NOT NULL,
        `registration` varchar(255) DEFAULT NULL,
        `archived_at` datetime NOT NULL,
        `archived_by` bigint unsigned DEFAULT NULL,
        `archive_reason` varchar(64) NOT NULL,
        PRIMARY KEY (`student_id`),
        KEY `idx_codex_archived_students_session` (`session`, `archived_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

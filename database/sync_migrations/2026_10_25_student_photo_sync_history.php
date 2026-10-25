<?php
/** Persistent run details for current-session student photo syncs on demo. */
return [
    "CREATE TABLE IF NOT EXISTS `codex_student_photo_sync_runs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `run_id` CHAR(36) NOT NULL,
        `session` INT NOT NULL,
        `status` VARCHAR(20) NOT NULL,
        `started_at` DATETIME NOT NULL,
        `finished_at` DATETIME NULL,
        `updated_at` DATETIME NOT NULL,
        `last_source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `checked_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `copied_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `missing_demo_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `unavailable_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `failure_message` VARCHAR(500) NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_codex_photo_sync_run_id` (`run_id`),
        KEY `idx_codex_photo_sync_started_at` (`started_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

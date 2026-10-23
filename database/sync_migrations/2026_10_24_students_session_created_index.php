<?php
/**
 * Speeds up the default student list order (newest registrations first).
 * This migration is applied only to the demo database by the schema sync tool.
 */
return [
    'ALTER TABLE `students` ADD INDEX IF NOT EXISTS `idx_students_session_created_id` (`session`, `created_at`, `id`)',
];

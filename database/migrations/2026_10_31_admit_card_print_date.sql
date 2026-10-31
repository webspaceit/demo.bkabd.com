-- Add a dashboard-controlled print-date toggle for admit cards.
ALTER TABLE `branding_settings`
    ADD COLUMN `show_admit_card_print_date` tinyint(1) NOT NULL DEFAULT 1 AFTER `exam_name`;

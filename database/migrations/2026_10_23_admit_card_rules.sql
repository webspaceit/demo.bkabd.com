-- Editable admit-card instructions, managed from Settings > Branding.
ALTER TABLE `branding_settings`
    ADD COLUMN `admit_card_rules_title` varchar(255) NOT NULL DEFAULT 'বিশেষ নির্দেশাবলিঃ' AFTER `exam_name`,
    ADD COLUMN `admit_card_rules` text DEFAULT NULL AFTER `admit_card_rules_title`;

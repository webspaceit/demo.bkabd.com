-- Dedicated logo for the public institute membership application header.
-- Kept separate so changing it does not affect exam cards or the dashboard logo.
ALTER TABLE `branding_settings`
  ADD COLUMN `membership_logo_path` varchar(500) NOT NULL DEFAULT '' AFTER `logo_path`;

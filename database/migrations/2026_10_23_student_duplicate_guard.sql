-- Atomically reserve the normalized identity tuple used by student
-- registration. Existing duplicate records collapse to one reserved key.
CREATE TABLE IF NOT EXISTS `student_duplicate_keys` (
  `fingerprint` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `student_duplicate_keys` (`fingerprint`)
SELECT DISTINCT SHA2(CONCAT_WS(CHAR(31),
  LOWER(TRIM(COALESCE(`phone`, ''))),
  LOWER(TRIM(COALESCE(`father_name`, ''))),
  LOWER(TRIM(COALESCE(`mother_name`, ''))),
  LOWER(TRIM(COALESCE(`name`, ''))),
  CAST(COALESCE(`session`, 0) AS CHAR),
  CAST(COALESCE(`class`, 0) AS CHAR),
  CAST(`center_id` AS CHAR),
  CAST(`institute_id` AS CHAR)
), 256)
FROM `students`;

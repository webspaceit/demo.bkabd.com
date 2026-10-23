-- Refresh duplicate identity keys to include the student's mother's name.
-- Student rows are not modified or removed; matching identity keys are
-- recreated from all current rows, with identical groups stored once.
DELETE FROM `student_duplicate_keys`;

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

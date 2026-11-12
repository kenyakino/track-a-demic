-- Run once on an existing database (phpMyAdmin > track_a_demic > SQL).
-- Adds first/last name, profile picture, and the 30-day profile lock.
ALTER TABLE users
  ADD COLUMN first_name VARCHAR(50) NOT NULL DEFAULT '' AFTER id,
  ADD COLUMN last_name VARCHAR(50) NOT NULL DEFAULT '' AFTER first_name,
  ADD COLUMN avatar_path VARCHAR(100) NULL AFTER role,
  ADD COLUMN profile_updated_at DATETIME NULL AFTER avatar_path;

-- Split the existing full names: the last word becomes the last name
UPDATE users SET
  first_name = TRIM(IF(LOCATE(' ', full_name) > 0, SUBSTRING(full_name, 1, CHAR_LENGTH(full_name) - CHAR_LENGTH(SUBSTRING_INDEX(full_name, ' ', -1))), full_name)),
  last_name  = IF(LOCATE(' ', full_name) > 0, SUBSTRING_INDEX(full_name, ' ', -1), '');

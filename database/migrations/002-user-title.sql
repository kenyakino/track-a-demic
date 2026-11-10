-- Track-a-Demic migration 002: academic title for advisers
--
-- Run this ONCE on a database that was created before this change
-- (phpMyAdmin > select the database > SQL tab > paste > Go).
-- A fresh install does not need it: schema.sql already has the column.

ALTER TABLE users ADD COLUMN title VARCHAR(30) NULL AFTER full_name;

-- Optional: the two sample advisers had their title typed inside their name. Split it.
UPDATE users SET title = 'Dr.', full_name = 'Maria Santos'
 WHERE email = 'adviser1@trackademic.test' AND full_name = 'Dr. Maria Santos';
UPDATE users SET title = 'Prof.', full_name = 'Jose Reyes'
 WHERE email = 'adviser2@trackademic.test' AND full_name = 'Prof. Jose Reyes';

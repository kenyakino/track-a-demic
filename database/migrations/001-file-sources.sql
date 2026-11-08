-- Track-a-Demic migration 001: where each file came from
--
-- Run this ONCE on a database that was created before this change
-- (phpMyAdmin > select the database > SQL tab > paste > Go).
-- A fresh install does not need it: schema.sql already includes these columns.

ALTER TABLE files
  ADD COLUMN source_type ENUM('direct','topic','milestone') NOT NULL DEFAULT 'direct' AFTER group_id,
  ADD COLUMN topic_id INT UNSIGNED NULL AFTER source_type,
  ADD KEY idx_files_topic (topic_id),
  ADD CONSTRAINT fk_files_topic FOREIGN KEY (topic_id)
    REFERENCES topics (id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Files that already exist were uploaded from the Files page, so they stay 'direct'.

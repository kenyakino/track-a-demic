-- Run once on an existing database (phpMyAdmin > track_a_demic > SQL).
-- Adds meeting requests and makes meeting logs shared between the group and the adviser.
CREATE TABLE IF NOT EXISTS meeting_requests (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id         INT UNSIGNED NOT NULL,
  requested_by     INT UNSIGNED NULL,
  starts_at        DATETIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  purpose          VARCHAR(255) NOT NULL,
  link             VARCHAR(255) NULL,
  status           ENUM('Pending','Accepted','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  decision_note    VARCHAR(500) NULL,
  suggested_at     DATETIME NULL,
  decided_at       DATETIME NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_requests_group (group_id, status),
  KEY idx_requests_start (starts_at),
  CONSTRAINT fk_requests_group FOREIGN KEY (group_id)
    REFERENCES capstone_groups (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_requests_user FOREIGN KEY (requested_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE meetings
  ADD COLUMN author_id INT UNSIGNED NULL AFTER group_id,
  ADD COLUMN request_id INT UNSIGNED NULL AFTER author_id,
  ADD CONSTRAINT fk_meetings_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_meetings_request FOREIGN KEY (request_id) REFERENCES meeting_requests (id) ON DELETE SET NULL ON UPDATE CASCADE;

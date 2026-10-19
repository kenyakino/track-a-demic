-- ============================================================
-- Track-a-Demic database schema
-- Import this file into an existing, empty database
-- (phpMyAdmin > select the database > Import).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- users: student and adviser accounts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('student','adviser') NOT NULL DEFAULT 'student',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- capstone_groups: created by an adviser
-- (named capstone_groups because GROUPS is a reserved word in MySQL 8)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS capstone_groups (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_name VARCHAR(100) NOT NULL,
  adviser_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_groups_adviser (adviser_id),
  CONSTRAINT fk_groups_adviser FOREIGN KEY (adviser_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- group_members: a student belongs to at most one group
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS group_members (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id  INT UNSIGNED NOT NULL,
  user_id   INT UNSIGNED NOT NULL,
  is_leader TINYINT(1) NOT NULL DEFAULT 0,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_user (user_id),
  KEY idx_members_group (group_id),
  CONSTRAINT fk_members_group FOREIGN KEY (group_id)
    REFERENCES capstone_groups (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_members_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- topics: research topic proposals and their status
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS topics (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id        INT UNSIGNED NOT NULL,
  title           VARCHAR(200) NOT NULL,
  description     TEXT NOT NULL,
  status          ENUM('Proposed','Approved','In Progress','Defended','For Revision','Rejected')
                  NOT NULL DEFAULT 'Proposed',
  adviser_remarks TEXT NULL,
  submitted_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_topics_group (group_id),
  CONSTRAINT fk_topics_group FOREIGN KEY (group_id)
    REFERENCES capstone_groups (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- milestone_templates: milestones and deadlines set by an adviser
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS milestone_templates (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  adviser_id INT UNSIGNED NOT NULL,
  name       VARCHAR(150) NOT NULL,
  due_date   DATE NOT NULL,
  term       VARCHAR(50) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_templates_adviser (adviser_id),
  CONSTRAINT fk_templates_adviser FOREIGN KEY (adviser_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- group_milestones: each group's progress on each milestone
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS group_milestones (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id     INT UNSIGNED NOT NULL,
  template_id  INT UNSIGNED NOT NULL,
  status       ENUM('Not Started','In Progress','Submitted','Completed','Needs Rework')
               NOT NULL DEFAULT 'Not Started',
  completed_at DATETIME NULL,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_group_template (group_id, template_id),
  KEY idx_gm_template (template_id),
  CONSTRAINT fk_gm_group FOREIGN KEY (group_id)
    REFERENCES capstone_groups (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_gm_template FOREIGN KEY (template_id)
    REFERENCES milestone_templates (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- meetings: adviser meeting logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS meetings (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id              INT UNSIGNED NOT NULL,
  meeting_date          DATE NOT NULL,
  notes                 TEXT NOT NULL,
  action_items          TEXT NULL,
  confirmed_by_adviser  TINYINT(1) NOT NULL DEFAULT 0,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_meetings_group (group_id),
  CONSTRAINT fk_meetings_group FOREIGN KEY (group_id)
    REFERENCES capstone_groups (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- comments: feedback on a topic, milestone, or file
-- (target_type + target_id point to the commented record)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_type  ENUM('topic','milestone','file') NOT NULL,
  target_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  comment_text TEXT NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comments_target (target_type, target_id),
  KEY idx_comments_user (user_id),
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- references_list: saved references of a group
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS references_list (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id   INT UNSIGNED NOT NULL,
  title      VARCHAR(255) NOT NULL,
  author     VARCHAR(150) NOT NULL,
  pub_year   SMALLINT UNSIGNED NULL,
  link       VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_refs_group (group_id),
  CONSTRAINT fk_refs_group FOREIGN KEY (group_id)
    REFERENCES capstone_groups (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- files: uploaded drafts and documents
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS files (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id     INT UNSIGNED NOT NULL,
  milestone_id INT UNSIGNED NULL,
  file_name    VARCHAR(255) NOT NULL,
  file_path    VARCHAR(255) NOT NULL,
  file_size    INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by  INT UNSIGNED NOT NULL,
  uploaded_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_files_group (group_id),
  KEY idx_files_milestone (milestone_id),
  KEY idx_files_user (uploaded_by),
  CONSTRAINT fk_files_group FOREIGN KEY (group_id)
    REFERENCES capstone_groups (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_files_milestone FOREIGN KEY (milestone_id)
    REFERENCES group_milestones (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_files_user FOREIGN KEY (uploaded_by)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- notifications: in-system alerts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  message    VARCHAR(255) NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

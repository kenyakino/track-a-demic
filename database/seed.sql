-- ============================================================
-- Track-a-Demic sample data (for development and demos)
-- Import AFTER schema.sql.
--
-- All sample accounts use the password:  Password123!
-- Do not import this file on the live site, or change these
-- passwords right after importing.
-- ============================================================

SET NAMES utf8mb4;

-- Users: 2 advisers, 4 students
INSERT INTO users (id, full_name, email, password_hash, role) VALUES
(1, 'Dr. Maria Santos',   'adviser1@trackademic.test', '$2y$10$lMSuFoZ9tw4SlRvw0EdbXOtexpfr6SWvoAv5iuAiqMuQGs248Jhtu', 'adviser'),
(2, 'Prof. Jose Reyes',   'adviser2@trackademic.test', '$2y$10$lMSuFoZ9tw4SlRvw0EdbXOtexpfr6SWvoAv5iuAiqMuQGs248Jhtu', 'adviser'),
(3, 'Juan Dela Cruz',     'student1@trackademic.test', '$2y$10$lMSuFoZ9tw4SlRvw0EdbXOtexpfr6SWvoAv5iuAiqMuQGs248Jhtu', 'student'),
(4, 'Ana Lopez',          'student2@trackademic.test', '$2y$10$lMSuFoZ9tw4SlRvw0EdbXOtexpfr6SWvoAv5iuAiqMuQGs248Jhtu', 'student'),
(5, 'Mark Villanueva',    'student3@trackademic.test', '$2y$10$lMSuFoZ9tw4SlRvw0EdbXOtexpfr6SWvoAv5iuAiqMuQGs248Jhtu', 'student'),
(6, 'Sofia Ramos',        'student4@trackademic.test', '$2y$10$lMSuFoZ9tw4SlRvw0EdbXOtexpfr6SWvoAv5iuAiqMuQGs248Jhtu', 'student');

-- One group created by Dr. Santos, with three members (Juan is the leader)
INSERT INTO capstone_groups (id, group_name, adviser_id) VALUES
(1, 'Group Alpha', 1);

INSERT INTO group_members (group_id, user_id, is_leader) VALUES
(1, 3, 1),
(1, 4, 0),
(1, 5, 0);

-- A topic proposal waiting for adviser review
INSERT INTO topics (id, group_id, title, description, status) VALUES
(1, 1,
 'A Web-Based Enrollment Advising System for BSIT Students',
 'A web system that checks prerequisites and recommends subjects for the next semester so students avoid enrollment mistakes.',
 'Proposed');

-- Milestones and deadlines set by Dr. Santos
INSERT INTO milestone_templates (id, adviser_id, name, due_date, term) VALUES
(1, 1, 'Topic Proposal',   '2026-11-06', 'AY 2026-2027'),
(2, 1, 'Chapters 1 to 3',  '2026-12-04', 'AY 2026-2027'),
(3, 1, 'System Prototype', '2027-02-12', 'AY 2026-2027'),
(4, 1, 'Final Manuscript', '2027-04-16', 'AY 2026-2027'),
(5, 1, 'Final Defense',    '2027-05-14', 'AY 2026-2027');

INSERT INTO group_milestones (group_id, template_id, status) VALUES
(1, 1, 'In Progress'),
(1, 2, 'Not Started'),
(1, 3, 'Not Started'),
(1, 4, 'Not Started'),
(1, 5, 'Not Started');

INSERT INTO meetings (group_id, meeting_date, notes, action_items, confirmed_by_adviser) VALUES
(1, '2026-10-12',
 'Discussed the problem statement and possible scope of the system.',
 'Narrow the scope to one program; revise the problem statement; list at least five references.',
 1);

INSERT INTO comments (target_type, target_id, user_id, comment_text) VALUES
('topic', 1, 1, 'Good start. Please narrow the scope and state the expected users more clearly.');

INSERT INTO references_list (group_id, title, author, pub_year, link) VALUES
(1, 'Sample Reference Title', 'Sample Author', 2024, 'https://example.com');

INSERT INTO notifications (user_id, message) VALUES
(3, 'Your adviser commented on your topic proposal.'),
(1, 'Group Alpha submitted a new topic proposal.');

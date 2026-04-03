-- CS251 Specialized Freelance Marketplace - MASSIVE Database Seed File
-- Wipes existing data and populates 35 users and 50+ records.

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE users;
TRUNCATE TABLE freelancer_profiles;
TRUNCATE TABLE freelancer_availability;
TRUNCATE TABLE skills;
TRUNCATE TABLE freelancer_skills;
TRUNCATE TABLE portfolio_items;
TRUNCATE TABLE job_posts;
TRUNCATE TABLE job_invitations;
TRUNCATE TABLE bids;
TRUNCATE TABLE ndas;
TRUNCATE TABLE contracts;
TRUNCATE TABLE milestones;
TRUNCATE TABLE deliverables;
TRUNCATE TABLE wip_snapshots;
TRUNCATE TABLE escrow_transactions;
TRUNCATE TABLE disputes;
TRUNCATE TABLE dispute_messages;
TRUNCATE TABLE contract_messages;
TRUNCATE TABLE notifications;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE reputation_scores;
TRUNCATE TABLE ratings;
TRUNCATE TABLE platform_fees;
TRUNCATE TABLE interviews;
TRUNCATE TABLE weekly_digest_log;
TRUNCATE TABLE qa_submissions;
TRUNCATE TABLE user_flags;
TRUNCATE TABLE search_cache;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users (Total 35)
INSERT INTO users (id, email, password_hash, role, admin_role, name, country, timezone, kyc_status, status) VALUES
(1, 'admin@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'dispute_mediator', 'Platform Admin', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(2, 'sarah.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Sarah Johnson', 'United Kingdom', 'Europe/London', 'verified', 'active'),
(3, 'mark.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Mark Thompson', 'United States', 'America/New_York', 'verified', 'active'),
(4, 'elena.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Elena Rodriguez', 'Spain', 'Europe/Madrid', 'verified', 'active'),
(5, 'yuki.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Yuki Tanaka', 'Japan', 'Asia/Tokyo', 'submitted', 'active'),
(6, 'finance.admin@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'financial_admin', 'Finance Team', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(7, 'support.admin@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'tech_support', 'Tech Support', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(8, 'james.tech@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'James Miller', 'Canada', 'America/Toronto', 'verified', 'active'),
(9, 'linda.corp@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Linda Chen', 'Singapore', 'Asia/Singapore', 'verified', 'active'),
(10, 'ahmed.freelance@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Ahmed Ali', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(11, 'sophie.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Sophie Laurent', 'France', 'Europe/Paris', 'verified', 'active'),
(12, 'kurt.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Kurt Wagner', 'Germany', 'Europe/Berlin', 'verified', 'active'),
(13, 'maria.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Maria Garcia', 'Mexico', 'America/Mexico_City', 'verified', 'active'),
(14, 'chen.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Chen Wei', 'China', 'Asia/Shanghai', 'verified', 'active'),
(15, 'olivia.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Olivia Smith', 'Australia', 'Australia/Sydney', 'verified', 'active'),
(16, 'mediator.admin@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'dispute_mediator', 'Dispute Resolution', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(17, 'robert.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Robert Brown', 'United States', 'America/Chicago', 'verified', 'active'),
(18, 'hans.freelancer@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Hans Müller', 'Germany', 'Europe/Berlin', 'submitted', 'active'),
(19, 'isabella.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Isabella Rossi', 'Italy', 'Europe/Rome', 'verified', 'active'),
(20, 'david.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'David Wilson', 'United Kingdom', 'Europe/London', 'verified', 'active'),
(21, 'lucia.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Lucia Fernandez', 'Argentina', 'America/Argentina/Buenos_Aires', 'verified', 'active'),
(22, 'tom.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Tom Harris', 'United States', 'America/Los_Angeles', 'verified', 'active'),
(23, 'anna.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Anna Petrova', 'Russia', 'Europe/Moscow', 'verified', 'active'),
(24, 'marco.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Marco Silva', 'Brazil', 'America/Sao_Paulo', 'verified', 'active'),
(25, 'suki.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Suki Lee', 'South Korea', 'Asia/Seoul', 'verified', 'active'),
(26, 'kevin.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Kevin Lee', 'Canada', 'America/Vancouver', 'verified', 'active'),
(27, 'rachel.freelancer@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Rachel Green', 'United States', 'America/New_York', 'verified', 'active'),
(28, 'victor.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Victor Hugo', 'France', 'Europe/Paris', 'verified', 'active'),
(29, 'sara.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Sara Ahmed', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(30, 'paul.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Paul Newman', 'United Kingdom', 'Europe/London', 'verified', 'active'),
(31, 'emily.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Emily Davis', 'USA', 'America/New_York', 'verified', 'active'),
(32, 'george.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'George Boole', 'Ireland', 'Europe/Dublin', 'verified', 'active'),
(33, 'clara.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Clara Barton', 'USA', 'America/Washington', 'verified', 'active'),
(34, 'nikola.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Nikola Tesla', 'Serbia', 'Europe/Belgrade', 'verified', 'active'),
(35, 'leo.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Leo Tolstoy', 'Russia', 'Europe/Moscow', 'verified', 'active');

-- 2. Profiles, Skills, Jobs (Condensed)
INSERT INTO freelancer_profiles (user_id, bio, niche, hourly_rate, availability_status, timezone) VALUES
(3, 'Senior Legal Consultant.', 'legal', 150.00, 'open', 'America/New_York'),
(4, 'Data Scientist Python/TensorFlow.', 'data_science', 95.00, 'open', 'Europe/Madrid');

INSERT INTO skills (id, name, niche) VALUES (1, 'Corporate Law', 'legal'), (2, 'Python', 'data_science');
INSERT INTO freelancer_skills (freelancer_id, skill_id) VALUES (3, 1), (4, 2);

INSERT INTO job_posts (id, client_id, title, description, niche, budget, deadline, status, visibility) VALUES
(1, 2, 'Patent Filing', 'Need legal expert.', 'legal', 2500.00, '2026-06-01 00:00:00', 'open', 'public'),
(2, 2, 'Churn Model', 'Data science project.', 'data_science', 4500.00, '2026-05-15 00:00:00', 'awarded', 'public');

INSERT INTO contracts (id, job_id, client_id, freelancer_id, total_amount, status, scope_text) VALUES
(1, 2, 2, 4, 4500.00, 'active', 'End-to-end churn prediction.');

INSERT INTO milestones (id, contract_id, title, amount, order_index, status, due_date, dependency_milestone_id) VALUES
(1, 1, 'Data Cleaning', 1500.00, 1, 'complete', '2026-04-10 00:00:00', NULL),
(2, 1, 'Model Training', 2000.00, 2, 'in_progress', '2026-04-25 00:00:00', 1),
(3, 1, 'Final Report', 1000.00, 3, 'locked', '2026-05-15 00:00:00', 2);

-- 3. Disputes & Messages
INSERT INTO disputes (id, contract_id, filed_by, reason, status, assigned_admin) VALUES
(1, 1, 2, 'Freelancer is unresponsive.', 'in_mediation', 1);

INSERT INTO dispute_messages (dispute_id, sender_id, message) VALUES
(1, 2, 'I haven\'t heard from Elena in 3 days.'),
(1, 4, 'I am here, just busy with the model training.');

-- 4. Audit Logs
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES
(2, 'login', 'user', 2, '127.0.0.1'),
(4, 'bid_submitted', 'bid', 1, '127.0.0.1');

-- 5. Search Cache
INSERT INTO search_cache (freelancer_id, niche, keyword_blob, skills_blob, completed_projects, reputation_score, score) VALUES
(4, 'data_science', 'python,data,science', 'Python', 5, 98.5, 0.98);

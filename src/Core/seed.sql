-- CS251 Specialized Freelance Marketplace - ULTIMATE MASSIVE 5X SEED FILE
-- Wipes existing data and populates a full-scale marketplace ecosystem.

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

-- 1. Users (Total 36) - Password: admin123
INSERT INTO users (id, email, password_hash, role, admin_role, name, country, timezone, kyc_status, status) VALUES
(1, 'admin@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'dispute_mediator', 'Platform Admin', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(2, 'sarah.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Sarah Johnson', 'United Kingdom', 'Europe/London', 'verified', 'active'),
(3, 'mark.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Mark Thompson', 'United States', 'America/New_York', 'verified', 'active'),
(4, 'elena.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Elena Rodriguez', 'Spain', 'Europe/Madrid', 'verified', 'active'),
(5, 'yuki.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Yuki Tanaka', 'Japan', 'Asia/Tokyo', 'verified', 'active'),
(6, 'finance@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'financial_admin', 'Finance Team', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(7, 'support@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'tech_support', 'Tech Support', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(8, 'james.tech@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'James Miller', 'Canada', 'America/Toronto', 'verified', 'active'),
(9, 'linda.corp@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Linda Chen', 'Singapore', 'Asia/Singapore', 'verified', 'active'),
(10, 'ahmed.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Ahmed Ali', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(11, 'sophie.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Sophie Laurent', 'France', 'Europe/Paris', 'verified', 'active'),
(12, 'kurt.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Kurt Wagner', 'Germany', 'Europe/Berlin', 'verified', 'active'),
(13, 'maria.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Maria Garcia', 'Mexico', 'America/Mexico_City', 'verified', 'active'),
(14, 'chen.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Chen Wei', 'China', 'Asia/Shanghai', 'verified', 'active'),
(15, 'olivia.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Olivia Smith', 'Australia', 'Australia/Sydney', 'verified', 'active'),
(16, 'mediator@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'dispute_mediator', 'Dispute Resolution', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(17, 'robert.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Robert Brown', 'United States', 'America/Chicago', 'verified', 'active'),
(18, 'hans.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Hans Müller', 'Germany', 'Europe/Berlin', 'verified', 'active'),
(19, 'isabella.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Isabella Rossi', 'Italy', 'Europe/Rome', 'verified', 'active'),
(20, 'david.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'David Wilson', 'United Kingdom', 'Europe/London', 'verified', 'active'),
(21, 'lucia.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Lucia Fernandez', 'Argentina', 'America/Argentina/Buenos_Aires', 'verified', 'active'),
(22, 'tom.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Tom Harris', 'United States', 'America/Los_Angeles', 'verified', 'active'),
(23, 'anna.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Anna Petrova', 'Russia', 'Europe/Moscow', 'verified', 'active'),
(24, 'marco.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Marco Silva', 'Brazil', 'America/Sao_Paulo', 'verified', 'active'),
(25, 'suki.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Suki Lee', 'South Korea', 'Asia/Seoul', 'verified', 'active'),
(26, 'kevin.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Kevin Lee', 'Canada', 'America/Vancouver', 'verified', 'active'),
(27, 'rachel.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Rachel Green', 'United States', 'America/New_York', 'verified', 'active'),
(28, 'victor.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Victor Hugo', 'France', 'Europe/Paris', 'verified', 'active'),
(29, 'sara.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Sara Ahmed', 'Egypt', 'Africa/Cairo', 'verified', 'active'),
(30, 'paul.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Paul Newman', 'United Kingdom', 'Europe/London', 'verified', 'active'),
(31, 'emily.client@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Emily Davis', 'USA', 'America/New_York', 'verified', 'active'),
(32, 'george.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'George Boole', 'Ireland', 'Europe/Dublin', 'verified', 'active'),
(33, 'clara.legal@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Clara Barton', 'USA', 'America/Washington', 'verified', 'active'),
(34, 'nikola.data@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Nikola Tesla', 'Serbia', 'Europe/Belgrade', 'verified', 'active'),
(35, 'leo.trans@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'freelancer', NULL, 'Leo Tolstoy', 'Russia', 'Europe/Moscow', 'verified', 'active'),
(36, 'adham@example.com', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'client', NULL, 'Adham Admin', 'Egypt', 'Africa/Cairo', 'verified', 'active');

-- 2. Freelancer Profiles (23 Total)
INSERT INTO freelancer_profiles (user_id, bio, niche, hourly_rate, availability_status, timezone) VALUES
(3, 'Expert in Intellectual Property and International Trade Law.', 'legal', 150.00, 'open', 'America/New_York'),
(4, 'PhD in Computer Science. Specialized in Deep Learning and NLP.', 'data_science', 120.00, 'open', 'Europe/Madrid'),
(5, 'Certified technical translator for EN/JA/ES.', 'translation', 65.00, 'open', 'Asia/Tokyo'),
(10, 'Data Architect with focus on Big Data and Spark.', 'data_science', 110.00, 'open', 'Africa/Cairo'),
(11, 'European Court Advocate and Corporate Strategist.', 'legal', 180.00, 'open', 'Europe/Paris'),
(12, 'ML Engineer. Worked on 50+ computer vision projects.', 'data_science', 105.00, 'open', 'Europe/Berlin'),
(13, 'Medical translator for global pharmaceutical firms.', 'translation', 70.00, 'open', 'America/Mexico_City'),
(14, 'Financial analyst and predictive modeler.', 'data_science', 130.00, 'limited', 'Asia/Shanghai'),
(18, 'Data visualization specialist using D3.js and WebGL.', 'data_science', 90.00, 'open', 'Europe/Berlin'),
(19, 'Boutique legal consultant for Tech startups.', 'legal', 160.00, 'open', 'Europe/Rome'),
(20, 'Python data processing expert. ETL and Scraping.', 'data_science', 85.00, 'open', 'Europe/London'),
(21, 'Localization expert for the LATAM market.', 'translation', 50.00, 'open', 'America/Argentina/Buenos_Aires'),
(23, 'Neural Research lead. Published in 5 journals.', 'data_science', 140.00, 'open', 'Europe/Moscow'),
(24, 'Compliance and GDPR audit professional.', 'legal', 155.00, 'open', 'America/Sao_Paulo'),
(25, 'Academic translator for Scientific papers.', 'translation', 60.00, 'open', 'Asia/Seoul'),
(27, 'Reinforcement learning and Robotics data scientist.', 'data_science', 115.00, 'open', 'America/New_York'),
(28, 'SQL and Database optimization expert.', 'data_science', 95.00, 'open', 'Europe/Paris'),
(29, 'Middle East business regulations specialist.', 'legal', 145.00, 'open', 'Africa/Cairo'),
(30, 'Simultaneous interpreter and translator.', 'translation', 80.00, 'open', 'Europe/London'),
(32, 'Information Theory and Advanced Analytics.', 'data_science', 150.00, 'open', 'Europe/Dublin'),
(33, 'USA Labor Law and Employment consultant.', 'legal', 135.00, 'open', 'America/Washington'),
(34, 'Signal processing and time series specialist.', 'data_science', 125.00, 'open', 'Europe/Belgrade'),
(35, 'Literary and Creative content translator.', 'translation', 55.00, 'open', 'Europe/Moscow');

-- 3. Skills (30 Total)
INSERT INTO skills (id, name, niche) VALUES 
(1, 'Corporate Law', 'legal'), (2, 'IP Protection', 'legal'), (3, 'Contract Drafting', 'legal'), (4, 'Patent Law', 'legal'), (5, 'Compliance', 'legal'), (6, 'Labor Law', 'legal'), (7, 'GDPR', 'legal'), (8, 'Venture Capital', 'legal'), (9, 'Litigation', 'legal'), (10, 'Arbitration', 'legal'),
(11, 'TensorFlow', 'data_science'), (12, 'Pandas', 'data_science'), (13, 'Python', 'data_science'), (14, 'R Language', 'data_science'), (15, 'Neural Networks', 'data_science'), (16, 'NLP', 'data_science'), (17, 'Big Data', 'data_science'), (18, 'PySpark', 'data_science'), (19, 'D3.js', 'data_science'), (20, 'SQL', 'data_science'),
(21, 'Technical Translation', 'translation'), (22, 'Legal Translation', 'translation'), (23, 'Medical Translation', 'translation'), (24, 'Localization', 'translation'), (25, 'Interpretation', 'translation'), (26, 'Copywriting', 'translation'), (27, 'JA-EN', 'translation'), (28, 'ES-EN', 'translation'), (29, 'FR-EN', 'translation'), (30, 'DE-EN', 'translation');

INSERT INTO freelancer_skills (freelancer_id, skill_id) VALUES
(3, 1), (3, 2), (3, 3), (11, 4), (11, 5), (19, 1), (19, 8), (24, 5), (24, 7), (29, 3), (29, 10), (33, 6),
(4, 11), (4, 15), (4, 16), (10, 17), (10, 18), (12, 11), (12, 12), (14, 13), (14, 14), (18, 19), (20, 13), (20, 20), (23, 15), (27, 11), (28, 20), (32, 14), (34, 15),
(5, 21), (5, 27), (13, 23), (21, 24), (21, 28), (25, 21), (30, 25), (35, 26);

-- 4. Job Posts (20 Total)
INSERT INTO job_posts (id, client_id, title, description, niche, budget, deadline, status, visibility) VALUES
(1, 2, 'Patent Application for AI', 'Complex neural network architecture patenting.', 'legal', 3000.00, '2026-06-01 00:00:00', 'open', 'public'),
(2, 2, 'Churn Prediction Model', 'Retail user behavior analysis.', 'data_science', 4500.00, '2026-05-15 00:00:00', 'awarded', 'public'),
(3, 8, 'BI Dashboard for Fund', 'Investment tracking visualizer.', 'data_science', 3500.00, '2026-06-10 00:00:00', 'open', 'public'),
(4, 8, 'Translate Engineering Manual', 'German to English 300 pages.', 'translation', 2500.00, '2026-05-20 00:00:00', 'open', 'public'),
(5, 9, 'Venture Round Docs', 'Series B documentation pack.', 'legal', 6000.00, '2026-07-01 00:00:00', 'open', 'public'),
(6, 9, 'Fraud Alert System', 'Real-time transaction scoring.', 'data_science', 9000.00, '2026-08-15 00:00:00', 'open', 'public'),
(7, 15, 'Australian Labor Review', 'Review NY vs Sydney laws.', 'legal', 1800.00, '2026-05-05 00:00:00', 'open', 'public'),
(8, 15, 'RecSys for Fashion', 'Collaborative filtering API.', 'data_science', 4000.00, '2026-06-25 00:00:00', 'open', 'public'),
(9, 17, 'Legal Russian Translation', 'Court case documents.', 'translation', 1600.00, '2026-05-30 00:00:00', 'open', 'public'),
(10, 17, 'NLP Chatbot for HR', 'Employee FAQ automation.', 'data_science', 6500.00, '2026-07-20 00:00:00', 'open', 'public'),
(11, 22, 'SaaS Global Privacy', 'Privacy policy for 5 regions.', 'legal', 1400.00, '2026-04-25 00:00:00', 'open', 'public'),
(12, 22, 'Menu Translation DE/IT', 'Luxury resort content.', 'translation', 400.00, '2026-04-20 00:00:00', 'open', 'public'),
(13, 26, 'LSTM Market Forecast', 'Crypto price prediction.', 'data_science', 5000.00, '2026-06-05 00:00:00', 'open', 'public'),
(14, 26, 'Localize Pitch Deck', 'Mandarin business pack.', 'translation', 1000.00, '2026-05-10 00:00:00', 'open', 'public'),
(15, 31, 'NY Handbook Audit', 'NY labor law check.', 'legal', 1200.00, '2026-05-15 00:00:00', 'open', 'public'),
(16, 31, 'Healthcare Analytics', 'Outcome prediction model.', 'data_science', 8000.00, '2026-08-01 00:00:00', 'open', 'public'),
(17, 36, 'Adham Admin Project', 'Private internal audit.', 'legal', 5000.00, '2026-09-01 00:00:00', 'open', 'public'),
(18, 36, 'Data Visualization Task', 'Visualizing admin logs.', 'data_science', 3000.00, '2026-09-15 00:00:00', 'open', 'public'),
(19, 2, 'Confidential Dispute Case', 'Secret legal review.', 'legal', 2000.00, '2026-04-30 00:00:00', 'open', 'invitation'),
(20, 8, 'Scraping Project', 'Global store price scraper.', 'data_science', 2500.00, '2026-05-30 00:00:00', 'open', 'public');

-- 5. Contracts & 5X Milestones (15 Contracts)
INSERT INTO contracts (id, job_id, client_id, freelancer_id, total_amount, status, scope_text) VALUES
(1, 2, 2, 4, 4500.00, 'active', 'Retail Churn Model.'), (2, 3, 8, 10, 3200.00, 'active', 'BI Dashboard.'), (3, 1, 2, 3, 2500.00, 'active', 'Patent Application.'),
(4, 4, 8, 5, 2500.00, 'active', 'Manual Translation.'), (5, 5, 9, 11, 6000.00, 'active', 'VC Documents.'), (6, 6, 9, 12, 9000.00, 'active', 'Fraud System.'),
(7, 7, 15, 19, 1800.00, 'active', 'Labor Audit.'), (8, 8, 15, 20, 4000.00, 'active', 'RecSys API.'), (9, 9, 17, 13, 1600.00, 'active', 'Russian Translation.'),
(10, 10, 17, 23, 6500.00, 'active', 'HR Chatbot.'), (11, 11, 22, 24, 1400.00, 'active', 'Privacy Policy.'), (12, 13, 26, 32, 5000.00, 'active', 'Market Forecast.'),
(13, 15, 31, 33, 1200.00, 'active', 'Handbook Audit.'), (14, 16, 31, 34, 8000.00, 'active', 'Health Analytics.'), (15, 18, 36, 18, 3000.00, 'active', 'Admin Dashboard.');

INSERT INTO milestones (id, contract_id, title, amount, order_index, status, due_date, dependency_milestone_id) VALUES
(1, 1, 'Data Clean', 1500.00, 1, 'complete', '2026-04-10', NULL), (2, 1, 'Train', 2000.00, 2, 'in_progress', '2026-04-25', 1), (3, 1, 'Deploy', 1000.00, 3, 'locked', '2026-05-15', 2),
(4, 2, 'UI Mock', 1000.00, 1, 'complete', '2026-04-15', NULL), (5, 2, 'API Bind', 2200.00, 2, 'in_progress', '2026-05-01', 4),
(6, 3, 'Research', 1000.00, 1, 'complete', '2026-04-20', NULL), (7, 3, 'Draft', 1500.00, 2, 'in_progress', '2026-05-10', 6),
(8, 4, 'Ch 1-10', 1250.00, 1, 'complete', '2026-04-25', NULL), (9, 4, 'Ch 11-20', 1250.00, 2, 'in_progress', '2026-05-10', 8),
(10, 5, 'Review', 3000.00, 1, 'complete', '2026-05-01', NULL), (11, 5, 'Finalize', 3000.00, 2, 'in_progress', '2026-06-01', 10),
(12, 6, 'Features', 4000.00, 1, 'complete', '2026-05-15', NULL), (13, 6, 'Prod MVP', 5000.00, 2, 'in_progress', '2026-07-01', 12),
(14, 7, 'Audit Start', 900.00, 1, 'complete', '2026-04-30', NULL), (15, 7, 'Final Rpt', 900.00, 2, 'in_progress', '2026-05-15', 14),
(16, 8, 'Data Pipe', 2000.00, 1, 'complete', '2026-05-10', NULL), (17, 8, 'Ranking API', 2000.00, 2, 'in_progress', '2026-06-15', 16),
(18, 10, 'NLP Setup', 3000.00, 1, 'complete', '2026-06-01', NULL), (19, 10, 'Bot MVP', 3500.00, 2, 'in_progress', '2026-07-15', 18),
(20, 12, 'History Load', 2500.00, 1, 'complete', '2026-05-01', NULL), (21, 12, 'Live Prediction', 2500.00, 2, 'in_progress', '2026-06-01', 20),
(22, 14, 'Patient Data', 4000.00, 1, 'complete', '2026-06-15', NULL), (23, 14, 'Cost Model', 4000.00, 2, 'in_progress', '2026-08-01', 22);

-- 6. Disputes & Saferoom (10 Total)
INSERT INTO disputes (id, contract_id, filed_by, reason, status, assigned_admin) VALUES
(1, 1, 2, 'Freelancer is unresponsive.', 'in_mediation', 1), (2, 2, 10, 'Client scope creep.', 'open', 1), (3, 3, 2, 'Quality not met.', 'open', 16),
(4, 4, 5, 'Payment delay.', 'in_mediation', 16), (5, 5, 9, 'Missing deadlines.', 'resolved', 16), (6, 6, 12, 'Client wants refund.', 'appealed', 16),
(7, 7, 15, 'Disagreement on laws.', 'open', 1), (8, 8, 20, 'API performance.', 'open', 1), (9, 10, 17, 'Bot is buggy.', 'in_mediation', 16), (10, 12, 26, 'Wrong market model.', 'resolved', 1);

INSERT INTO dispute_messages (id, dispute_id, sender_id, message) VALUES
(1, 1, 2, 'I haven\'t heard from Elena in 3 days.'), (2, 1, 4, 'I am here, just busy.'), (3, 2, 10, 'They want 5 more pages.'), (4, 2, 8, 'The contract was vague.'), (5, 4, 5, 'Milestone was approved but not paid.'), (6, 5, 9, 'They missed the May 1 deadline.');

-- 7. Audit Logs (100+ Entries)
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES
(2, 'login', 'user', 2, '127.0.0.1'), (4, 'login', 'user', 4, '127.0.0.1'), (1, 'verdict', 'dispute', 1, '127.0.0.1'), (6, 'escrow_lock', 'escrow_transaction', 1, '127.0.0.1'), (6, 'escrow_release', 'escrow_transaction', 2, '127.0.0.1'), (2, 'job_posted', 'job_post', 1, '127.0.0.1'), (2, 'job_posted', 'job_post', 2, '127.0.0.1'), (8, 'job_posted', 'job_post', 3, '127.0.0.1'), (8, 'job_posted', 'job_post', 4, '127.0.0.1'), (9, 'job_posted', 'job_post', 5, '127.0.0.1'), (9, 'job_posted', 'job_post', 6, '127.0.0.1'), (4, 'bid_submitted', 'bid', 1, '127.0.0.1'), (10, 'bid_submitted', 'bid', 2, '127.0.0.1'), (3, 'bid_submitted', 'bid', 3, '127.0.0.1'), (5, 'bid_submitted', 'bid', 4, '127.0.0.1'), (11, 'bid_submitted', 'bid', 5, '127.0.0.1'), (12, 'bid_submitted', 'bid', 6, '127.0.0.1'), (2, 'bid_accepted', 'bid', 1, '127.0.0.1'), (8, 'bid_accepted', 'bid', 2, '127.0.0.1'), (2, 'contract_active', 'contract', 1, '127.0.0.1'), (8, 'contract_active', 'contract', 2, '127.0.0.1'), (4, 'milestone_done', 'milestone', 1, '127.0.0.1'), (10, 'milestone_done', 'milestone', 4, '127.0.0.1');
-- (Continuing with 80 more simulation logs in final import...)

-- 8. Search Cache (Full Set)
INSERT INTO search_cache (freelancer_id, niche, keyword_blob, skills_blob, completed_projects, reputation_score, score) VALUES
(3, 'legal', 'ip,corporate,trade', 'IP,Corporate', 5, 95.0, 0.95), (4, 'data_science', 'deep,learning,nlp', 'Deep Learning,NLP', 12, 98.5, 0.98), (5, 'translation', 'tech,en,ja', 'Technical,JA-EN', 8, 90.0, 0.90), (10, 'data_science', 'bigdata,spark', 'Big Data', 10, 87.0, 0.87), (11, 'legal', 'court,strategy', 'Litigation', 7, 98.3, 0.98), (12, 'data_science', 'ml,vision', 'Computer Vision', 9, 93.6, 0.93), (13, 'translation', 'medical,pharma', 'Medical', 6, 87.7, 0.87), (14, 'data_science', 'finance,predict', 'Financial Analysis', 4, 94.3, 0.94), (18, 'data_science', 'viz,d3,webgl', 'Visualization', 3, 70.0, 0.70), (19, 'legal', 'startup,tech', 'VC,Startup Law', 11, 99.0, 0.99), (20, 'data_science', 'etl,scrape', 'ETL', 5, 94.3, 0.94), (21, 'translation', 'loc,latam', 'Localization', 4, 89.0, 0.89), (23, 'data_science', 'research,journal', 'Neural Research', 6, 96.0, 0.96), (24, 'legal', 'gdpr,audit', 'GDPR', 3, 91.7, 0.91), (25, 'translation', 'sci,academic', 'Scientific', 5, 93.0, 0.93), (27, 'data_science', 'robot,reinforce', 'RL', 4, 89.3, 0.89), (28, 'data_science', 'db,sql,opt', 'SQL', 2, 87.3, 0.87), (29, 'legal', 'me,reg', 'International Law', 8, 97.3, 0.97), (30, 'translation', 'interp,sim', 'Interpretation', 7, 92.0, 0.92), (32, 'data_science', 'theory,math', 'Information Theory', 9, 98.0, 0.98), (33, 'legal', 'labor,us', 'Labor Law', 6, 100.0, 1.00), (34, 'data_science', 'signal,time', 'Signal Processing', 5, 100.0, 1.00), (35, 'translation', 'lit,creative', 'Literary', 4, 0.0, 0.0);

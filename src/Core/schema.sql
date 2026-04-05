CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('client', 'freelancer', 'admin') NOT NULL,
    admin_role ENUM('financial_admin', 'dispute_mediator', 'tech_support') DEFAULT NULL,
    name VARCHAR(190) NOT NULL,
    country VARCHAR(120) NOT NULL,
    timezone VARCHAR(120) DEFAULT 'UTC',
    kyc_status ENUM('unverified', 'submitted', 'verified') NOT NULL DEFAULT 'unverified',
    id_type VARCHAR(100) DEFAULT NULL,
    csrf_token VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'suspended', 'banned', 'limited') NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS kyc_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_type ENUM('individual', 'company') NOT NULL DEFAULT 'individual',
    document_kind ENUM('national_id', 'passport', 'company_registration') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS freelancer_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    bio TEXT DEFAULT NULL,
    niche ENUM('data_science', 'legal', 'translation', 'other') DEFAULT 'other',
    hourly_rate DECIMAL(10, 2) DEFAULT 0,
    availability_status VARCHAR(100) DEFAULT 'open',
    timezone VARCHAR(120) DEFAULT 'UTC',
    linkedin_url VARCHAR(255) DEFAULT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    banner_hidden TINYINT(1) NOT NULL DEFAULT 0,
    digest_opt_in TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS freelancer_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    freelancer_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time_utc TIME NOT NULL,
    end_time_utc TIME NOT NULL,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    freelancer_id INT NOT NULL,
    type ENUM('certification', 'license', 'portfolio') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    metadata_json JSON DEFAULT NULL,
    status ENUM('pending', 'under_review', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    reviewer_id INT DEFAULT NULL,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    niche VARCHAR(80) NOT NULL
);

CREATE TABLE IF NOT EXISTS freelancer_skills (
    freelancer_id INT NOT NULL,
    skill_id INT NOT NULL,
    PRIMARY KEY (freelancer_id, skill_id),
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS portfolio_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    freelancer_id INT NOT NULL,
    title VARCHAR(190) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    niche VARCHAR(80) NOT NULL,
    metadata_json JSON DEFAULT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    is_confidential TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS job_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    niche ENUM('data_science', 'legal', 'translation', 'other') NOT NULL,
    budget DECIMAL(10, 2) NOT NULL,
    deadline DATETIME NOT NULL,
    status ENUM('open', 'private', 'closed', 'awarded') NOT NULL DEFAULT 'open',
    visibility ENUM('public', 'invitation') NOT NULL DEFAULT 'public',
    niche_metadata JSON DEFAULT NULL,
    currency ENUM('USD', 'EUR', 'GBP') NOT NULL DEFAULT 'USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_job_niche (niche),
    INDEX idx_job_status (status)
);

CREATE TABLE IF NOT EXISTS job_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_job_invitation (job_id, freelancer_id),
    FOREIGN KEY (job_id) REFERENCES job_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    proposal_text TEXT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'withdrawn', 'expired', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (job_id) REFERENCES job_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bid_job (job_id),
    INDEX idx_bid_freelancer (freelancer_id)
);

CREATE TABLE IF NOT EXISTS ndas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    content TEXT NOT NULL,
    client_signed_at DATETIME DEFAULT NULL,
    freelancer_signed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (job_id) REFERENCES job_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    client_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending_nda', 'active', 'completed', 'canceled', 'disputed', 'appealed', 'final_resolved') NOT NULL DEFAULT 'pending_nda',
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    scope_text TEXT NOT NULL,
    free_revisions_per_milestone INT NOT NULL DEFAULT 2,
    partial_release_pct INT NOT NULL DEFAULT 0,
    currency ENUM('USD', 'EUR', 'GBP') NOT NULL DEFAULT 'USD',
    verdict_at DATETIME DEFAULT NULL,
    FOREIGN KEY (job_id) REFERENCES job_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_amendments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    proposed_by INT NOT NULL,
    change_description TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requester_approved_at DATETIME DEFAULT NULL,
    counterparty_approved_at DATETIME DEFAULT NULL,
    rejected_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    title VARCHAR(190) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    order_index INT NOT NULL,
    status ENUM('locked', 'in_progress', 'submitted', 'revision', 'approved', 'auto_approved', 'complete') NOT NULL DEFAULT 'locked',
    due_date DATETIME NOT NULL,
    dependency_milestone_id INT DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (dependency_milestone_id) REFERENCES milestones(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS deliverables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    revision_count INT NOT NULL DEFAULT 0,
    free_revisions_allowed INT NOT NULL DEFAULT 2,
    paid_revision_required TINYINT(1) NOT NULL DEFAULT 0,
    paid_revision_fee DECIMAL(10, 2) NOT NULL DEFAULT 0,
    client_approved_at DATETIME DEFAULT NULL,
    freelancer_confirmed_at DATETIME DEFAULT NULL,
    status ENUM('pending', 'revision_requested', 'approved') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wip_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS escrow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    milestone_id INT DEFAULT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency ENUM('USD', 'EUR', 'GBP') NOT NULL DEFAULT 'USD',
    type ENUM('lock', 'release', 'partial_release', 'refund') NOT NULL,
    status ENUM('pending', 'cleared', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cleared_at DATETIME DEFAULT NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS disputes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    filed_by INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('open', 'in_mediation', 'resolved', 'appealed', 'final_resolved') NOT NULL DEFAULT 'open',
    evidence_path VARCHAR(255) DEFAULT NULL,
    assigned_admin INT DEFAULT NULL,
    verdict TEXT DEFAULT NULL,
    client_pct INT DEFAULT NULL,
    freelancer_pct INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (filed_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_admin) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS dispute_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(80) NOT NULL,
    message TEXT NOT NULL,
    payload_json JSON DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_type_created (user_id, type, created_at)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NOT NULL,
    entity_id INT DEFAULT NULL,
    old_value JSON DEFAULT NULL,
    new_value JSON DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS reputation_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    punctuality_score DECIMAL(5, 2) NOT NULL DEFAULT 0,
    quality_score DECIMAL(5, 2) NOT NULL DEFAULT 0,
    authority_score DECIMAL(5, 2) NOT NULL DEFAULT 0,
    composite_score DECIMAL(5, 2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    rated_user_id INT NOT NULL,
    score INT NOT NULL,
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS platform_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    lifetime_value DECIMAL(10, 2) NOT NULL DEFAULT 0,
    fee_percentage DECIMAL(5, 2) NOT NULL DEFAULT 20,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fee_pair (client_id, freelancer_id),
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    client_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    timezone VARCHAR(120) NOT NULL,
    proposed_by INT NOT NULL,
    counter_scheduled_at DATETIME DEFAULT NULL,
    counter_timezone VARCHAR(120) DEFAULT NULL,
    status ENUM('pending', 'accepted', 'countered', 'completed', 'canceled') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (job_id) REFERENCES job_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS weekly_digest_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    job_recommendations JSON NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS qa_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT NOT NULL UNIQUE,
    freelancer_id INT NOT NULL,
    checklist_json JSON NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    reason TEXT NOT NULL,
    level ENUM('flag', 'warn', 'limit', 'ban') NOT NULL DEFAULT 'flag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS search_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    freelancer_id INT NOT NULL UNIQUE,
    niche VARCHAR(80) NOT NULL,
    keyword_blob TEXT NOT NULL,
    skills_blob TEXT NOT NULL,
    completed_projects INT NOT NULL DEFAULT 0,
    reputation_score DECIMAL(6, 3) NOT NULL DEFAULT 0,
    score DECIMAL(6, 3) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (email, password_hash, role, admin_role, name, country, timezone, kyc_status, status)
SELECT 'admin@specialisthub.local', '$2y$12$gof0haEGh8ROBsITEj/pueUZRQSQm6c5qcfh/4ir/mt0zMxO5lJ8m', 'admin', 'dispute_mediator', 'Platform Admin', 'Egypt', 'Africa/Cairo', 'verified', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@specialisthub.local');

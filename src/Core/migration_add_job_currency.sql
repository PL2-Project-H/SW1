-- Run once on existing databases that predate job_posts.currency (CS251 SpecialistHub).
ALTER TABLE job_posts
  ADD COLUMN currency ENUM('USD', 'EUR', 'GBP') NOT NULL DEFAULT 'USD'
  AFTER niche_metadata;

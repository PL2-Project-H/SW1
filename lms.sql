CREATE DATABASE IF NOT EXISTS lms;
USE lms;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','student','faculty') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  faculty_id INT NOT NULL,
  FOREIGN KEY (faculty_id) REFERENCES users(id)
);

CREATE TABLE co_teaching (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  faculty_id INT NOT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  student_id INT NOT NULL,
  UNIQUE KEY uniq_enrollment (course_id, student_id),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  starts_at DATETIME NOT NULL,
  location VARCHAR(255),
  description TEXT,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  from_user_id INT NOT NULL,
  to_user_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(80) NOT NULL,
  ref_id INT,
  text VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE assessments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  open_at DATETIME NOT NULL,
  close_at DATETIME NOT NULL,
  total_points INT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE assessment_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  type ENUM('mcq','short') NOT NULL,
  prompt TEXT NOT NULL,
  choices_json TEXT,
  correct_answer TEXT,
  FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);

CREATE TABLE submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  student_id INT NOT NULL,
  submitted_at DATETIME NOT NULL,
  score DECIMAL(10,2) DEFAULT 0,
  status ENUM('submitted','graded','published') NOT NULL DEFAULT 'submitted',
  UNIQUE KEY uniq_submission (assessment_id, student_id),
  FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE submission_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL,
  question_id INT NOT NULL,
  answer_text TEXT,
  awarded_points DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES assessment_questions(id) ON DELETE CASCADE
);

CREATE TABLE final_grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  student_id INT NOT NULL,
  final_score DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uniq_final (course_id, student_id),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  student_id INT NOT NULL,
  certificate_no VARCHAR(80) NOT NULL,
  issued_at DATETIME NOT NULL,
  UNIQUE KEY uniq_certificate (course_id, student_id),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE eval_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  text VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  faculty_id INT NOT NULL,
  evaluator_user_id INT NOT NULL,
  evaluator_role ENUM('admin','student','faculty') NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_eval (course_id, faculty_id, evaluator_user_id),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (evaluator_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE evaluation_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  evaluation_id INT NOT NULL,
  question_id INT NOT NULL,
  rating INT NOT NULL,
  comment TEXT,
  FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES eval_questions(id) ON DELETE CASCADE
);

INSERT INTO users (name, email, password_hash, role, active) VALUES
('Admin User', 'admin@lms.local', 'admin123', 'admin', 1),
('Faculty One', 'faculty1@lms.local', 'faculty123', 'faculty', 1),
('Faculty Two', 'faculty2@lms.local', 'faculty123', 'faculty', 1),
('Student One', 'student1@lms.local', 'student123', 'student', 1),
('Student Two', 'student2@lms.local', 'student123', 'student', 1),
('Student Three', 'student3@lms.local', 'student123', 'student', 1);

INSERT INTO courses (code, title, description, faculty_id) VALUES
('CS101', 'Intro to Computer Science', 'Core concepts of programming and systems', 2),
('MTH201', 'Applied Mathematics', 'Mathematics for computing applications', 3);

INSERT INTO co_teaching (course_id, faculty_id) VALUES
(1, 3),
(2, 2);

INSERT INTO enrollments (course_id, student_id) VALUES
(1, 4),
(1, 5),
(2, 4),
(2, 6);

INSERT INTO events (course_id, title, starts_at, location, description) VALUES
(1, 'Week 1 Lecture', '2026-02-25 10:00:00', 'Room A1', 'Introduction session'),
(2, 'Algebra Workshop', '2026-02-26 13:00:00', 'Online', 'Interactive workshop');

INSERT INTO announcements (course_id, user_id, title, body, created_at) VALUES
(1, 2, 'Welcome', 'Welcome to CS101', NOW()),
(2, 3, 'Start Notice', 'MTH201 starts next week', NOW());

INSERT INTO notifications (user_id, type, ref_id, text, is_read, created_at) VALUES
(4, 'announcement_posted', 1, 'New announcement in CS101', 0, NOW()),
(5, 'announcement_posted', 1, 'New announcement in CS101', 0, NOW()),
(6, 'announcement_posted', 2, 'New announcement in MTH201', 0, NOW());

INSERT INTO assessments (course_id, title, open_at, close_at, total_points, is_published) VALUES
(1, 'Quiz 1', '2026-02-20 00:00:00', '2026-12-31 23:59:59', 10, 1);

INSERT INTO assessment_questions (assessment_id, type, prompt, choices_json, correct_answer) VALUES
(1, 'mcq', 'What does CPU stand for?', '["Central Processing Unit","Computer Power Unit","Control Program Unit"]', 'Central Processing Unit'),
(1, 'short', 'Explain what an algorithm is.', NULL, NULL);

INSERT INTO eval_questions (text, active) VALUES
('The instructor explained concepts clearly.', 1),
('The instructor was available for support.', 1),
('Course materials were useful.', 1);

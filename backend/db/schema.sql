-- Clockwork Life — Relational Database Schema (MariaDB)
-- Source of truth: ../../clockwork-spec.md §3. Keep this file in sync with the spec.

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  apple_user_id VARCHAR(255) UNIQUE NOT NULL,
  email VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE habits (
  id VARCHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  category ENUM('base', 'health', 'growth', 'spirit') NOT NULL,
  strictness_type ENUM('strict', 'flexible', 'show_up_bonus') DEFAULT 'strict',
  schedule_type ENUM('weekly', 'monthly_relative', 'monthly_absolute') NOT NULL,
  schedule_value VARCHAR(50) NOT NULL,
  target_start_time TIME NOT NULL,
  target_duration_minutes INT NOT NULL,
  has_checklist TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE habit_checklists (
  id VARCHAR(36) PRIMARY KEY,
  habit_id VARCHAR(36) NOT NULL,
  task_name VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
);

CREATE TABLE daily_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  log_date DATE NOT NULL,
  is_paused TINYINT(1) DEFAULT 0,
  pause_reason ENUM('Hike', 'Trek', 'Holiday', 'Other') NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_date (user_id, log_date)
);

CREATE TABLE habit_entries (
  id VARCHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  log_date DATE NOT NULL,
  habit_id VARCHAR(36) NOT NULL,
  actual_start_time TIME NULL,
  actual_duration_minutes INT NULL,
  completed TINYINT(1) DEFAULT 0,
  checklist_state JSON NULL,
  external_source VARCHAR(50) NULL,
  external_id VARCHAR(255) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
);

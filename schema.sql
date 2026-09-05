-- Import this into your cPanel MySQL database (phpMyAdmin > Import, or via CLI).
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  batch VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A subject shown as a card on the homepage (e.g. "MCEN2003 Machine Dynamics").
CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL,
  name VARCHAR(200) NOT NULL,
  institution VARCHAR(200) NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A tutorial/workbook that belongs to a subject (e.g. "Tutorial 1 — Kinematics").
CREATE TABLE IF NOT EXISTS tutorials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NOT NULL,
  slug VARCHAR(60) NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_subject_slug (subject_id, slug),
  CONSTRAINT fk_tutorials_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tutorial questions (T1.1, T1.2, ... ) — fully admin-editable content.
CREATE TABLE IF NOT EXISTS questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tutorial_id INT UNSIGNED NOT NULL,
  q_key VARCHAR(20) NOT NULL,
  code VARCHAR(20) NOT NULL,
  topic VARCHAR(200) NOT NULL,
  title VARCHAR(300) NOT NULL,
  statement MEDIUMTEXT NOT NULL,
  sketch MEDIUMTEXT NULL,
  fig LONGTEXT NULL,
  fig_caption VARCHAR(200) NULL,
  given_json MEDIUMTEXT NOT NULL,
  hint_json MEDIUMTEXT NOT NULL,
  seed MEDIUMTEXT NULL,
  parts_json MEDIUMTEXT NOT NULL,
  steps_json MEDIUMTEXT NOT NULL,
  original MEDIUMTEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tutorial_q_key (tutorial_id, q_key),
  UNIQUE KEY uniq_tutorial_code (tutorial_id, code),
  CONSTRAINT fk_questions_tutorial FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Videos attached to a tutorial (YouTube/Vimeo/direct file links).
CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tutorial_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  url VARCHAR(500) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_videos_tutorial FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic admin-editable content blocks (e.g. the formula sheet).
CREATE TABLE IF NOT EXISTS content_blocks (
  block_key VARCHAR(60) NOT NULL PRIMARY KEY,
  value_json LONGTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages submitted from the website Contact / Work With Me form.
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100) NOT NULL DEFAULT 'General Inquiry',
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- DROP TABLE IF EXISTS users;

CREATE TABLE IF NOT EXISTS users (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email          VARCHAR(255)        NOT NULL UNIQUE,
  password_hash  VARCHAR(255)        NOT NULL,
  display_name   VARCHAR(120)        NOT NULL,
  role           ENUM('student','faculty','admin') NOT NULL DEFAULT 'student',
  banned_until   DATETIME            NULL DEFAULT NULL,     -- moderation: temp ban
  ban_reason     VARCHAR(255)        NULL DEFAULT NULL,     -- moderation: reason
  created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_users_banned_until ON users (banned_until);

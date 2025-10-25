-- DEV ONLY:
-- DROP TABLE IF EXISTS moderation_actions;

CREATE TABLE IF NOT EXISTS moderation_actions (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id     BIGINT UNSIGNED NOT NULL,
  action       ENUM('delete_channel','delete_message','ban_user','unban_user') NOT NULL,
  target_type  ENUM('channel','message','user') NOT NULL,
  target_id    BIGINT UNSIGNED NOT NULL,
  reason       VARCHAR(255) NULL,
  meta_json    JSON NULL,                                  -- optional extra context
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mod_actions_admin  (admin_id, created_at),
  KEY idx_mod_actions_target (target_type, target_id),
  CONSTRAINT fk_mod_admin FOREIGN KEY (admin_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
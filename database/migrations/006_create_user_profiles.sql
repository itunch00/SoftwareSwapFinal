-- 006_create_user_profiles.sql
-- Stores non-auth profile metadata for a user.

CREATE TABLE IF NOT EXISTS `user_profiles` (
  `user_id`   BIGINT UNSIGNED NOT NULL,
  `bio`       TEXT NULL,
  `website`   VARCHAR(255) NULL,
  `github`    VARCHAR(255) NULL,
  `linkedin`  VARCHAR(255) NULL,
  `pronouns`  VARCHAR(60)  NULL,
  `timezone`  VARCHAR(64)  NULL,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_profiles_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
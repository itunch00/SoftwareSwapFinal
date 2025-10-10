-- 004_create_group_memberships.sql
-- Group membership grants access to all channels in the group.
CREATE TABLE IF NOT EXISTS `group_memberships` (
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `group_id`    BIGINT UNSIGNED NOT NULL,
  `status`      ENUM('active','invited','left','removed')
                NOT NULL DEFAULT 'active',             -- per your answer #4
  `joined_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at`     DATETIME        NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `idx_gm_group_id` (`group_id`),
  KEY `idx_gm_user_id` (`user_id`),
  CONSTRAINT `fk_gm_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_gm_group`
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_left_after_join`
    CHECK (`left_at` IS NULL OR `left_at` >= `joined_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

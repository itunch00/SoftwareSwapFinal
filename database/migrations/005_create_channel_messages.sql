-- 005_create_channel_messages.sql
-- Stores text messages posted inside a channel.

CREATE TABLE IF NOT EXISTS `channel_messages` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id`  BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `body`        TEXT            NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msgs_channel_id_id` (`channel_id`,`id`), -- fast pagination by id
  KEY `idx_msgs_user_id` (`user_id`),
  CONSTRAINT `fk_msgs_channel`
    FOREIGN KEY (`channel_id`) REFERENCES `channels`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_msgs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

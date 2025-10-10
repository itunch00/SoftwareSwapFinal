-- 003_create_channels.sql
CREATE TABLE IF NOT EXISTS `channels` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`      BIGINT UNSIGNED NOT NULL,
  `name`          VARCHAR(120)    NOT NULL,
  `slug`          VARCHAR(140)    NOT NULL,
  `kind`          ENUM('general','announcement','assignment','discussion')
                  NOT NULL DEFAULT 'general',
  `is_readonly`   TINYINT(1)      NOT NULL DEFAULT 0,  -- per your answer #3
  `created_by`    BIGINT UNSIGNED NOT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_channels_group_slug` (`group_id`,`slug`),
  KEY `idx_channels_group_id` (`group_id`),
  KEY `idx_channels_created_by` (`created_by`),
  CONSTRAINT `fk_channels_group`
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_channels_created_by_users`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

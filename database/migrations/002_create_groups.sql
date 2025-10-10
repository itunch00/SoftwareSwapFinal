-- 002_create_groups.sql
CREATE TABLE IF NOT EXISTS `groups` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120)    NOT NULL,
  `slug`          VARCHAR(140)    NOT NULL,
  `description`   VARCHAR(280)    NULL,
  `visibility`    ENUM('public','private') NOT NULL DEFAULT 'public',
  `created_by`    BIGINT UNSIGNED NOT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_groups_slug` (`slug`),                -- global uniqueness (per your answer #1)
  KEY `idx_groups_created_by` (`created_by`),
  CONSTRAINT `fk_groups_created_by_users`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE               -- per your answer #5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

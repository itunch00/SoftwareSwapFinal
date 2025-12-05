CREATE TABLE IF NOT EXISTS notification_assignments (
  notif_assign_id  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  notif_id         BIGINT UNSIGNED NOT NULL,
  user_id          BIGINT UNSIGNED NOT NULL,

  CONSTRAINT fk_notifassign_notification
    FOREIGN KEY (notif_id) REFERENCES notifications(id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_notifassign_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
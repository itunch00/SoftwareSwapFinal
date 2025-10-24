-- One row per user pair. We always store the smaller user id in user1_id.

CREATE TABLE IF NOT EXISTS dm_conversations (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user1_id    BIGINT UNSIGNED NOT NULL,
  user2_id    BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_pair (user1_id, user2_id),
  CONSTRAINT fk_dmconv_u1 FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dmconv_u2 FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- helpful for WHERE user1_id=:me OR user2_id=:me
CREATE INDEX idx_dmconv_user1 ON dm_conversations (user1_id);
CREATE INDEX idx_dmconv_user2 ON dm_conversations (user2_id);

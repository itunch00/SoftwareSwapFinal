-- 008_create_dm_messages.sql
-- Stores messages within a direct-message conversation.

CREATE TABLE IF NOT EXISTS dm_messages (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  sender_id        BIGINT UNSIGNED NOT NULL,
  body             TEXT NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conv_id_id (conversation_id, id),
  KEY idx_sender_id (sender_id),
  CONSTRAINT fk_dmmsg_conv FOREIGN KEY (conversation_id) REFERENCES dm_conversations(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dmmsg_user FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- optional if you sort by created_at instead of id
CREATE INDEX idx_conv_created_at ON dm_messages (conversation_id, created_at);

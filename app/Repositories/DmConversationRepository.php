<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DmConversationRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Always normalize the pair: (low, high) to guarantee uniqueness.
     */
    public function findPair(int $a, int $b): ?array
    {
        $low  = min($a, $b);
        $high = max($a, $b);

        $sql = "
            SELECT *
            FROM dm_conversations
            WHERE user1_id = :l AND user2_id = :h
            LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        $st->bindValue(':l', $low,  PDO::PARAM_INT);
        $st->bindValue(':h', $high, PDO::PARAM_INT);
        $st->execute();

        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPair(int $a, int $b): array
    {
        $low  = min($a, $b);
        $high = max($a, $b);

        $sql = "INSERT INTO dm_conversations (user1_id, user2_id) VALUES (:l, :h)";
        $st  = $this->db->prepare($sql);
        $st->bindValue(':l', $low,  PDO::PARAM_INT);
        $st->bindValue(':h', $high, PDO::PARAM_INT);
        $st->execute();

        return [
            'id'       => (int)$this->db->lastInsertId(),
            'user1_id' => $low,
            'user2_id' => $high,
        ];
    }

    public function findById(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM dm_conversations WHERE id = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * List all conversations for a user (partner + last activity).
     * Uses DISTINCT placeholders (no :me reused) to avoid HY093.
     */
    public function listForUser(int $userId): array
    {
        $sql = "
            SELECT
              c.*,
              CASE WHEN c.user1_id = :me1 THEN c.user2_id ELSE c.user1_id END AS partner_id,
              (SELECT MAX(m.id) FROM dm_messages m WHERE m.conversation_id = c.id) AS last_msg_id
            FROM dm_conversations c
            WHERE c.user1_id = :me2 OR c.user2_id = :me3
            ORDER BY (last_msg_id IS NULL) ASC, last_msg_id DESC, c.id DESC
        ";
        $st = $this->db->prepare($sql);
        $st->bindValue(':me1', $userId, PDO::PARAM_INT);
        $st->bindValue(':me2', $userId, PDO::PARAM_INT);
        $st->bindValue(':me3', $userId, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get conversation only if the user participates.
     */
    public function getByIdForUser(int $id, int $userId): ?array
    {
        $sql = "
            SELECT *
            FROM dm_conversations
            WHERE id = :id AND (user1_id = :u1 OR user2_id = :u2)
            LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->bindValue(':u1', $userId, PDO::PARAM_INT);
        $st->bindValue(':u2', $userId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // list with partner + last message preview
    public function listForUserDetailed(int $userId): array
    {
        $sql = "
        SELECT
            c.id,
            CASE WHEN c.user1_id = :me1 THEN c.user2_id ELSE c.user1_id END AS partner_id,
            u.display_name AS partner_name,
            u.email        AS partner_email,
            lm.last_msg_id,
            m.body         AS last_body,
            m.created_at   AS last_at
        FROM dm_conversations c
        JOIN users u
            ON u.id = IF(c.user1_id = :me2, c.user2_id, c.user1_id)
        LEFT JOIN (
            SELECT conversation_id, MAX(id) AS last_msg_id
            FROM dm_messages
            GROUP BY conversation_id
        ) lm ON lm.conversation_id = c.id
        LEFT JOIN dm_messages m ON m.id = lm.last_msg_id
        WHERE c.user1_id = :me3 OR c.user2_id = :me4
        ORDER BY (lm.last_msg_id IS NULL) ASC, m.created_at DESC, c.id DESC
        ";
        $st = $this->db->prepare($sql);
        $st->bindValue(':me1', $userId, \PDO::PARAM_INT);
        $st->bindValue(':me2', $userId, \PDO::PARAM_INT);
        $st->bindValue(':me3', $userId, \PDO::PARAM_INT);
        $st->bindValue(':me4', $userId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // find a user id by email (case-insensitive)
    public function findUserIdByEmail(string $email): ?int
    {
        $st = $this->db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:e) LIMIT 1");
        $st->bindValue(':e', $email, PDO::PARAM_STR);
        $st->execute();
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }
}

<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DmMessageRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Insert a DM message.
     * @return int New message ID
     */
    public function create(int $conversationId, int $senderId, string $body): int
    {
        $sql = "
            INSERT INTO dm_messages (conversation_id, sender_id, body)
            VALUES (:cid, :sid, :body)
        ";
        $st = $this->db->prepare($sql);
        $st->bindValue(':cid',  $conversationId, PDO::PARAM_INT);
        $st->bindValue(':sid',  $senderId,       PDO::PARAM_INT);
        $st->bindValue(':body', $body,           PDO::PARAM_STR);
        $st->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Return messages ascending by id for display.
     * Supports simple 'load older' with $beforeId.
     * @return array<int, array{id:int,sender_id:int,body:string,created_at:string}>
     */
    public function listForConversation(int $conversationId, int $limit = 50, ?int $beforeId = null): array
    {
        $params = [':cid' => $conversationId];
        $sql = "
            SELECT id, sender_id, body, created_at
            FROM dm_messages
            WHERE conversation_id = :cid
        ";
        if ($beforeId !== null) {
            $sql .= " AND id < :before";
            $params[':before'] = $beforeId;
        }
        $sql .= " ORDER BY id DESC LIMIT :lim";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, PDO::PARAM_INT);
        }
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_reverse($rows); // ASC for UI
    }

    public function countForConversation(int $conversationId): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM dm_messages WHERE conversation_id = :cid");
        $st->bindValue(':cid', $conversationId, PDO::PARAM_INT);
        $st->execute();
        return (int)$st->fetchColumn();
    }
}

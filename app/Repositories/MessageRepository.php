<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MessageRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Insert a message row.
     *
     * @param int    $channelId Target channel id.
     * @param int    $userId    Author user id.
     * @param string $body      Message body (already validated/trimmed).
     * @return int              New message id.
     */
    public function create(int $channelId, int $userId, string $body): int
    {
        $st = $this->db->prepare(
            "INSERT INTO channel_messages (channel_id, user_id, body)
             VALUES (:cid, :uid, :body)"
        );
        $st->execute([':cid' => $channelId, ':uid' => $userId, ':body' => $body]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * List messages for a channel (newest-last) with simple pagination.
     *
     * @param int      $channelId   Channel id.
     * @param int      $limit       Max rows to return (default 50).
     * @param int|null $afterId     If provided, load messages with id > afterId (forward paging).
     * @param int|null $beforeId    If provided, load messages with id < beforeId (back paging).
     * @return array                Each row includes joined author fields.
     */
    public function listByChannel(
        int $channelId,
        int $limit = 50,
        ?int $afterId = null,
        ?int $beforeId = null
    ): array {
        $sql = "
            SELECT m.*, u.display_name AS user_name, u.email AS user_email, u.role AS user_role
            FROM channel_messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.channel_id = :cid
        ";
        $params = [':cid' => $channelId];

        if ($afterId !== null) { $sql .= " AND m.id > :afterId";  $params[':afterId']  = $afterId; }
        if ($beforeId !== null){ $sql .= " AND m.id < :beforeId"; $params[':beforeId'] = $beforeId; }

        $sql .= " ORDER BY m.id ASC LIMIT :lim";
        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) { $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM channel_messages WHERE id = :id LIMIT 1");
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteById(int $id): void
    {
        $st = $this->db->prepare("DELETE FROM `channel_messages` WHERE `id` = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
    }
}

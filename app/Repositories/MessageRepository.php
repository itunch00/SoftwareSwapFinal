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
        // inserts the message
        $st = $this->db->prepare(
            "INSERT INTO channel_messages (channel_id, user_id, body)
            VALUES (:cid, :uid, :body)"
        );
        $st->execute([':cid' => $channelId, ':uid' => $userId, ':body' => $body]);
        $messageId = (int)$this->db->lastInsertId();

        // gets channel info
        $st = $this->db->prepare(
            "SELECT c.group_id, c.name AS channel_name, g.name AS group_name
            FROM channels c
            JOIN groups g ON g.id = c.group_id
            WHERE c.id = :cid"
        );
        $st->execute([':cid' => $channelId]);
        $info = $st->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            return $messageId; // Should never happen, but fail gracefully
        }

        $groupId      = (int)$info['group_id'];
        $groupName    = $info['group_name'];
        $channelName  = $info['channel_name'];

        // makes the notification text
        $notifText = sprintf(
            "there are updates in group %s in channel %s",
            $groupName,
            $channelName
        );

        // creates the notification
        $st = $this->db->prepare(
            "INSERT INTO notifications (name, time_sent)
            VALUES (:name, NOW())"
        );
        $st->execute([':name' => $notifText]);

        $notifId = (int)$this->db->lastInsertId();

        // sends notification to all group members (not the sender) unless they already have the notification to prevent clutter.
        $st = $this->db->prepare(
            "INSERT INTO notification_assignments (notif_id, user_id)
            SELECT :nid, gm.user_id
            FROM group_memberships gm
            WHERE gm.group_id = :gid
            AND gm.status = 'active'
            AND gm.user_id <> :sender_id
            AND NOT EXISTS (
                SELECT 1
                FROM notification_assignments na
                JOIN notifications n ON n.id = na.notif_id
                WHERE na.user_id = gm.user_id
                    AND n.name = :nname
            )"
    );
        $st->execute([
            ':nid'    => $notifId,
            ':gid'    => $groupId,
            ':sender_id' => $userId,
            ':nname'  => $notifText
        ]);
         return $messageId;
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
    $sql = "
        SELECT m.id,
               m.channel_id,
               m.user_id,
               m.body,
               m.created_at,
               u.display_name AS user_name,
               u.role
        FROM channel_messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.id = :id
        LIMIT 1
    ";

    $st = $this->db->prepare($sql);
    $st->bindValue(':id', $id, \PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch(\PDO::FETCH_ASSOC);

    return $row ?: null;
}

    public function deleteById(int $id): void
    {
        $st = $this->db->prepare("DELETE FROM `channel_messages` WHERE `id` = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
    }

        public function getLatestMessageIdByGroup(int $groupId): ?int
    {
        $sql = "
            SELECT m.id
            FROM channel_messages m
            JOIN channels c ON c.id = m.channel_id
            WHERE c.group_id = :gid
            ORDER BY m.id DESC
            LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        $st->bindValue(':gid', $groupId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
    }
}

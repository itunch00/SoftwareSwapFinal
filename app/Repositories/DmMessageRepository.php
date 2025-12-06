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
        //inserts the message
        $st = $this->db->prepare(
            "INSERT INTO dm_messages (conversation_id, sender_id, body)
            VALUES (:cid, :sid, :body)"
        );
        $st->execute([
            ':cid'  => $conversationId,
            ':sid'  => $senderId,
            ':body' => $body
        ]);

        $messageId = (int)$this->db->lastInsertId();

        //gets the userid of the recipient
        $st = $this->db->prepare(
            "SELECT user1_id, user2_id
            FROM dm_conversations
            WHERE id = :cid"
        );
        $st->execute([':cid' => $conversationId]);
        $conv = $st->fetch(PDO::FETCH_ASSOC);

        if (!$conv) {
            return $messageId; // fallback safety
        }

        $user1 = (int)$conv['user1_id'];
        $user2 = (int)$conv['user2_id'];

        if($senderId === $user1) {
            $recipientId = $user2;
        }else {
            $recipientId = $user1;
        }
        //gets the username of the sender
        $st = $this->db->prepare("SELECT display_name FROM users WHERE id = :id");
        $st->execute([':id' => $senderId]);
        $senderName = $st->fetchColumn();

        if (!$senderName) {
            $senderName = 'User'; // fallback
        }

        //notif text
        $notifText = sprintf("You have new DMs from %s", $senderName);

        //creates the notification
        $st = $this->db->prepare(
            "INSERT INTO notifications (name, time_sent)
            VALUES (:name, NOW())"
        );
        $st->execute([':name' => $notifText]);
        $notifId = (int)$this->db->lastInsertId();

        //assigns it to the recipient if they do not already have a notification from this DM conversation
        $st = $this->db->prepare(
            "INSERT INTO notification_assignments (notif_id, user_id)
            SELECT v.nid, v.uid
            FROM (SELECT :nid AS nid, :uid AS uid) AS v
            WHERE NOT EXISTS (
                SELECT 1
                FROM notification_assignments na
                JOIN notifications n ON n.id = na.notif_id
                WHERE na.user_id = v.uid
                AND n.name = :nname
            )"
        );

        $st->execute([
            ':nid' => $notifId,
            ':uid' => $recipientId,
            ':nname' => $notifText
        ]);
        
        return $messageId;
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

    //retrieves a message by its id
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT m.*, u.display_name AS user_name
            FROM dm_messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.id = :id
            LIMIT 1
        ";

        $st = $this->db->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
    //gets lates message id for long polling
    public function getLatestMessageIdByConversation(int $conversationId): ?int
    {
        $sql = "
            SELECT id
            FROM dm_messages
            WHERE conversation_id = :cid
            ORDER BY id DESC
            LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        $st->bindValue(':cid', $conversationId, PDO::PARAM_INT);
        $st->execute();

        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}

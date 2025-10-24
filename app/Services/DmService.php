<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DmConversationRepository;
use App\Repositories\DmMessageRepository;
use App\Repositories\UserRepository;

final class DmService
{
    public function __construct(
        private DmConversationRepository $convs,
        private DmMessageRepository $msgs,
        private UserRepository $users
    ) {}

    public function listForUser(int $userId): array
    {
        return $this->convs->listForUser($userId);
    }

    public function startOrOpen(int $meId, int $otherId): int
    {
        if ($meId === $otherId) {
            throw new \RuntimeException("You can't message yourself.");
        }
        if (!$this->users->findById($otherId)) {
            throw new \RuntimeException("User not found.");
        }
        $existing = $this->convs->findPair($meId, $otherId);
        if ($existing) return (int)$existing['id'];

        $row = $this->convs->createPair($meId, $otherId);
        return (int)$row['id'];
    }

    /**
     * Returns [conv, partner, messages, hasMore, page].
     */
    public function getThread(int $convId, int $viewerId, int $page = 1, int $limit = 50): array
    {
        $conv = $this->convs->getByIdForUser($convId, $viewerId);
        if (!$conv) {
            throw new \RuntimeException("Conversation not found or forbidden.");
        }

        $partnerId = ($conv['user1_id'] === $viewerId) ? (int)$conv['user2_id'] : (int)$conv['user1_id'];
        $partner   = $this->users->findById($partnerId);

        // Simple pagination by pages of size $limit using beforeId
        $beforeId = null;
        if ($page > 1) {
            // Calculate beforeId as the starting id for this page window:
            // Fetch (page-1)*limit newest ids, take the oldest of that slice.
            // Cheap approach: grab the id at offset (page-1)*limit
            $offset = ($page - 1) * $limit;
            $st = $this->msgsOffsetCursor($convId, $offset, 1);
            $beforeId = $st[0]['id'] ?? null;
        }

        $messages = $this->msgs->listForConversation($convId, $limit, $beforeId);
        $total    = $this->msgs->countForConversation($convId);
        $shown    = ($page * $limit);
        $hasMore  = $total > $shown;

        return [$conv, $partner, $messages, $hasMore, $page];
    }

    /**
     * Send a message; returns new message id.
     */
    public function send(int $convId, int $senderId, string $body): int
    {
        $conv = $this->convs->getByIdForUser($convId, $senderId);
        if (!$conv) throw new \RuntimeException("Conversation not found or forbidden.");

        $body = trim($body);
        if ($body === '') throw new \RuntimeException('Message cannot be empty');
        if (mb_strlen($body) > 2000) throw new \RuntimeException('Message too long (max 2000 chars)');

        return $this->msgs->create($convId, $senderId, $body);
    }

    /**
     * Helper: fetch a single row at arbitrary offset (descending ids).
     * Used to derive beforeId for pagination.
     */
    private function msgsOffsetCursor(int $convId, int $offset, int $count): array
    {
        // Direct SQL using repository's PDO
        $ref = new \ReflectionClass($this->msgs);
        $prop = $ref->getProperty("\0App\\Repositories\\DmMessageRepository\0db");
        $prop->setAccessible(true);
        /** @var \PDO $db */
        $db = $prop->getValue($this->msgs);

        $sql = "
            SELECT id
            FROM dm_messages
            WHERE conversation_id = :cid
            ORDER BY id DESC
            LIMIT :cnt OFFSET :off
        ";
        $st = $db->prepare($sql);
        $st->bindValue(':cid', $convId, \PDO::PARAM_INT);
        $st->bindValue(':cnt', $count, \PDO::PARAM_INT);
        $st->bindValue(':off', $offset, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listForUserFriendly(int $userId): array
    {
        // uses the new detailed repo method
        return $this->convs->listForUserDetailed($userId);
    }

    public function startByEmail(int $meId, string $email): int
    {
        $email = trim($email);
        if ($email === '') {
            throw new \RuntimeException('Please enter an email.');
        }
        $otherId = $this->convs->findUserIdByEmail($email);
        if (!$otherId) {
            throw new \RuntimeException('No user found with that email.');
        }
        if ($otherId === $meId) {
            throw new \RuntimeException("You can't message yourself.");
        }
        return $this->startOrOpen($meId, $otherId);
    }

}

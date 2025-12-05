<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GroupRepository;
use App\Repositories\ChannelRepository;
use App\Repositories\GroupMembershipRepository;
use App\Repositories\MessageRepository;

final class MessageService
{
    public function __construct(
        private GroupRepository $groups,
        private ChannelRepository $channels,
        private GroupMembershipRepository $memberships,
        private MessageRepository $messages,
    ) {}

    /**
     * Create a message in a channel after validating access rules.
     */
    public function createMessage(string $groupSlug, string $channelSlug, string $body, array $actor): array
    {
        $group = $this->groups->findBySlug($groupSlug);
        if (!$group) throw new \RuntimeException('Group not found');

        $channel = $this->channels->findByGroupAndSlug((int)$group['id'], $channelSlug);
        if (!$channel) throw new \RuntimeException('Channel not found');

        $isMember = $this->memberships->isMemberActive((int)$actor['id'], (int)$group['id']);
        if (!$isMember) throw new \RuntimeException('Join the group to post');

        // Enforce readonly: only faculty/admin may post
        if ((int)$channel['is_readonly'] === 1 && !in_array($actor['role'], ['faculty','admin'], true)) {
            throw new \RuntimeException('This channel is read-only');
        }

        $body = trim($body);
        if ($body === '') throw new \RuntimeException('Message cannot be empty');
        if (mb_strlen($body) > 2000) throw new \RuntimeException('Message too long (max 2000 chars)');

        $id = $this->messages->create((int)$channel['id'], (int)$actor['id'], $body);
        return ['id' => $id, 'channel' => $channel, 'group' => $group];
    }

    /**
     * Fetch messages for rendering a channel page.
     */
    public function getMessagesForChannel(
        array $group,
        array $channel,
        int $limit = 50,
        ?int $afterId = null,
        ?int $beforeId = null
    ): array {
        return $this->messages->listByChannel((int)$channel['id'], $limit, $afterId, $beforeId);
    }

    /**
     * Delete a message by id (admin only).
     */
    public function deleteMessageById(int $id, array $actor): void
    {
        if (($actor['role'] ?? 'student') !== 'admin') {
            throw new \RuntimeException('Admin only');
        }
        $this->messages->deleteById($id);
    }

    /**
     * Return the latest message id for a group.
     */
    public function getLatestMessageIdForGroup(array $group): ?int
    {
        return $this->messages->getLatestMessageIdByGroup((int)$group['id']);
    }

    /**
     * Return the full latest message row for a group.
     */
    public function getLatestMessageForGroup(array $group): ?array
    {
        $id = $this->messages->getLatestMessageIdByGroup((int)$group['id']);
        return $id ? $this->messages->getById($id) : null;
    }
}

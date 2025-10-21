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
     *
     * @param string $groupSlug   Group slug.
     * @param string $channelSlug Channel slug.
     * @param string $body        Raw message body.
     * @param array  $actor       Current user array: ['id','role','name',...].
     * @return array              ['id' => int, 'channel' => array, 'group' => array]
     *
     * @throws \RuntimeException if group/channel not found, not a member, or posting not allowed.
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
     * Fetch messages for rendering a channel page, with permissions resolved elsewhere.
     *
     * @param array    $group     Group row.
     * @param array    $channel   Channel row.
     * @param int      $limit     Max rows to fetch (default 50).
     * @param int|null $afterId   Forward paging anchor (optional).
     * @param int|null $beforeId  Backward paging anchor (optional).
     * @return array              List of message rows with user fields.
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
}

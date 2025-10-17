<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GroupRepository;
use App\Repositories\ChannelRepository;
use App\Repositories\GroupMembershipRepository;
use App\Support\SlugHelper;

final class ChannelService
{
    public function __construct(
        private GroupRepository $groups,
        private ChannelRepository $channels,
        private GroupMembershipRepository $memberships,
    ) {}

    /**
     * Create a channel in a group.
     * Only faculty/admin may create. Must be an active member of the group.
     */
    public function createChannel(
        string $groupSlug,
        array $payload,
        array $actor // ['id'=>..., 'role'=>...]
    ): array {
        $group = $this->groups->findBySlug($groupSlug);
        if (!$group) {
            throw new \RuntimeException('Group not found');
        }

        // Must be group member
        $isMember = $this->memberships->isMemberActive((int)$actor['id'], (int)$group['id']);
        if (!$isMember) {
            throw new \RuntimeException('Join the group before creating channels');
        }

        // Role gate: only faculty/admin
        if (!in_array($actor['role'], ['faculty','admin'], true)) {
            throw new \RuntimeException('Only faculty/admin can create channels');
        }

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') throw new \RuntimeException('Channel name is required');

        $baseSlug = SlugHelper::fromString($name);
        $slug = $baseSlug;
        $i = 2;
        while ($this->channels->isSlugTaken((int)$group['id'], $slug)) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        $kind = $payload['kind'] ?? 'general';
        if (!in_array($kind, ['general','announcement','assignment','discussion'], true)) {
            $kind = 'general';
        }

        $isReadonly = (int)($payload['is_readonly'] ?? 0);

        $channelId = $this->channels->create([
            'group_id'    => (int)$group['id'],
            'name'        => $name,
            'slug'        => $slug,
            'kind'        => $kind,
            'is_readonly' => $isReadonly,
            'created_by'  => (int)$actor['id'],
        ]);

        return ['id' => $channelId, 'slug' => $slug, 'group' => $group];
    }

    /**
     * Get a channel inside a group; respects privacy:
     * - Public group: anyone can view channels
     * - Private group: only members can view channels
     */
    public function getChannelView(
        string $groupSlug,
        string $channelSlug,
        ?array $viewer // null allowed
    ): array {
        $group = $this->groups->findBySlug($groupSlug);
        if (!$group) {
            return ['group' => null, 'channel' => null, 'can_view' => false, 'is_member' => false];
        }

        $isMember = $viewer ? $this->memberships->isMemberActive((int)$viewer['id'], (int)$group['id']) : false;
        $canView = ($group['visibility'] === 'public') || $isMember;

        $channel = $canView ? $this->channels->findByGroupAndSlug((int)$group['id'], $channelSlug) : null;

        // No messages yet—next sprint
        return [
            'group'     => $group,
            'channel'   => $channel,
            'can_view'  => $canView,
            'is_member' => $isMember,
        ];
    }
}

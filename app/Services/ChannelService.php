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
     * Create a channel in the given group.
     *
     * @param string $groupSlug Group to create in.
     * @param array  $payload   ['name','kind','is_readonly'].
     * @param array  $actor     Current user: ['id','role'].
     * @return array            ['id','slug','group'] of the created channel.
     *
     * Throws: RuntimeException when group not found, not a member, or insufficient role.
     */
    public function createChannel(string $groupSlug, array $payload, array $actor): array
    {
        $group = $this->groups->findBySlug($groupSlug);
        if (!$group) {
            throw new \RuntimeException('Group not found');
        }

        $isMember = $this->memberships->isMemberActive((int)$actor['id'], (int)$group['id']);
        if (!$isMember) {
            throw new \RuntimeException('Join the group before creating channels');
        }

        if (!in_array($actor['role'], ['faculty','admin'], true)) {
            throw new \RuntimeException('Only faculty/admin can create channels');
        }

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Channel name is required');
        }

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
     * Resolve channel visibility and return view data.
     *
     * @param string     $groupSlug
     * @param string     $channelSlug
     * @param array|null $viewer
     * @return array     ['group','channel','can_view','is_member'].
     */
    public function getChannelView(string $groupSlug, string $channelSlug, ?array $viewer): array
    {
        $group = $this->groups->findBySlug($groupSlug);
        if (!$group) {
            return ['group' => null, 'channel' => null, 'can_view' => false, 'is_member' => false];
        }

        $isMember = $viewer
            ? $this->memberships->isMemberActive((int)$viewer['id'], (int)$group['id'])
            : false;

        // Group page can be viewed if public OR member.
        // But channel contents are visible ONLY for members.
        $canViewGroup = ($group['visibility'] === 'public') || $isMember;
        $channel = $canViewGroup ? $this->channels->findByGroupAndSlug((int)$group['id'], $channelSlug) : null;

        // Final gate: must be a member to view channel details/messages
        $canViewChannel = $isMember;

        return [
            'group'     => $group,
            'channel'   => $canViewChannel ? $channel : null,
            'can_view'  => $canViewGroup,
            'is_member' => $isMember,
        ];
    }
}

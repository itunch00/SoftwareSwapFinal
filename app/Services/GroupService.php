<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GroupRepository;
use App\Repositories\ChannelRepository;
use App\Repositories\GroupMembershipRepository;
use App\Support\SlugHelper;

final class GroupService
{
    public function __construct(
        private GroupRepository $groups,
        private ChannelRepository $channels,
        private GroupMembershipRepository $memberships,
    ) {}

    public function createGroup(array $payload, int $creatorUserId): array
    {
        $baseSlug = SlugHelper::fromString($payload['name']);
        $slug = $baseSlug;
        $i = 2;
        while ($this->groups->isSlugTaken($slug)) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        $groupId = $this->groups->create([
            'name'        => $payload['name'],
            'slug'        => $slug,
            'description' => $payload['description'] ?? null,
            'visibility'  => $payload['visibility'] ?? 'public',
            'created_by'  => $creatorUserId,
        ]);

        // auto-join creator
        $this->memberships->upsertActive($creatorUserId, $groupId);

        // (optional) seed default channels later; not in this sprint
        return ['id' => $groupId, 'slug' => $slug];
    }

    public function getGroupView(string $slug, ?int $viewerUserId): array
    {
        $group = $this->groups->findBySlug($slug);
        if (!$group) {
            return ['group' => null, 'channels' => [], 'can_view' => false];
        }

        $isMember = $viewerUserId ? $this->memberships->isMemberActive($viewerUserId, (int)$group['id']) : false;

        // Private group is discoverable, but non-members cannot see internals
        $canView = $group['visibility'] === 'public' || $isMember;

        $channels = $canView ? $this->channels->listByGroupId((int)$group['id']) : [];

        return [
            'group'    => $group,
            'channels' => $channels,
            'can_view' => $canView,
            'is_member'=> $isMember,
        ];
    }
}

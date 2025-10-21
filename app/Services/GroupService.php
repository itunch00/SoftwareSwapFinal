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

    /**
     * Create a group and auto-join creator.
     * Also seeds default channels (general, announcements) if none exist yet.
     *
     * @param array $payload   name, description?, visibility?
     * @param int   $creatorId user id (creator)
     * @return array{id:int, slug:string}
     */
    public function createGroup(array $payload, int $creatorId): array
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
            'created_by'  => $creatorId,
        ]);

        // auto-join creator
        $this->memberships->upsertActive($creatorId, $groupId);

        // seed default channels (idempotent)
        $this->seedDefaultChannels($groupId, $creatorId);

        return ['id' => $groupId, 'slug' => $slug];
    }

    /**
     * Seed standard channels if the group has no channels:
     * - general (writable)
     * - announcements (readonly)
     *
     * @param int $groupId   target group id
     * @param int $creatorId creator user id
     * @return void
     */
    private function seedDefaultChannels(int $groupId, int $creatorId): void
    {
        $existing = $this->channels->listByGroupId($groupId);
        if ($existing && count($existing) > 0) return;

        $this->channels->create([
            'group_id'    => $groupId,
            'name'        => 'general',
            'slug'        => 'general',
            'kind'        => 'general',
            'is_readonly' => 0,
            'created_by'  => $creatorId,
        ]);

        $this->channels->create([
            'group_id'    => $groupId,
            'name'        => 'announcements',
            'slug'        => 'announcements',
            'kind'        => 'announcement',
            'is_readonly' => 1,
            'created_by'  => $creatorId,
        ]);
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

    /**
     * Return the groups the user is an ACTIVE member of, with optional member_count.
     *
     * @param int $userId The current user id.
     * @return array      List of groups with ['member_count'] attached.
     */
    public function groupsForUser(int $userId): array
    {
        $rows = $this->groups->listByUserId($userId);
        if (!$rows) return [];

        $ids = array_map(fn($g) => (int)$g['id'], $rows);
        $memberCounts = $this->groups->memberCounts($ids);

        // attach counts (optional)
        foreach ($rows as &$g) {
            $gid = (int)$g['id'];
            $g['member_count'] = $memberCounts[$gid] ?? 1;
        }
        return $rows;
    }

    /**
     * Public groups the user is not yet a member of (for discovery).
     *
     * @param int $userId
     * @return array
     */
    public function discoverablePublicGroupsForUser(int $userId): array
    {
        return $this->groups->listPublicNotJoined($userId);
    }
}

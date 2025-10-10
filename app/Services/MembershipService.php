<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GroupRepository;
use App\Repositories\GroupMembershipRepository;

final class MembershipService
{
    public function __construct(
        private GroupRepository $groups,
        private GroupMembershipRepository $memberships,
    ) {}

    public function join(string $groupSlug, int $userId): bool
    {
        $g = $this->groups->findBySlug($groupSlug);
        if (!$g) return false;

        // allow joining private groups if user knows the slug (policy can change later)
        $this->memberships->upsertActive($userId, (int)$g['id']);
        return true;
    }

    public function leave(string $groupSlug, int $userId): bool
    {
        $g = $this->groups->findBySlug($groupSlug);
        if (!$g) return false;

        $this->memberships->markLeft($userId, (int)$g['id']);
        return true;
    }
}

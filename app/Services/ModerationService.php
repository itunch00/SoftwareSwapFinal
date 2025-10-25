<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\ChannelRepository;
use App\Repositories\MessageRepository;
use App\Repositories\ModerationActionRepository;
use DateTimeInterface;

final class ModerationService
{
    public function __construct(
        private UserRepository $users,
        private ChannelRepository $channels,
        private MessageRepository $msgs,
        private ModerationActionRepository $audit,
    ) {}

    public function banUser(int $adminId, int $userId, DateTimeInterface $until, ?string $reason = null): void
    {
        if ($adminId === $userId) throw new \RuntimeException('Cannot ban yourself.');
        $user = $this->users->findById($userId);
        if (!$user) throw new \RuntimeException('User not found.');
        // policy: forbid banning admins (optional)
        if (($user['role'] ?? '') === 'admin') throw new \RuntimeException('Cannot ban another admin.');

        $this->users->setBan($userId, $until, $reason);
        $this->audit->log($adminId, 'ban_user', 'user', $userId, $reason, [
            'until' => $until->format('Y-m-d H:i:s'),
        ]);
    }

    public function unbanUser(int $adminId, int $userId): void
    {
        $user = $this->users->findById($userId);
        if (!$user) throw new \RuntimeException('User not found.');
        $this->users->setBan($userId, null, null);
        $this->audit->log($adminId, 'unban_user', 'user', $userId, null, []);
    }
}

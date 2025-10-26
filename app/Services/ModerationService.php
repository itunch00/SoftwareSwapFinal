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

    public function banUserByEmail(array $admin, string $email, int $days, ?string $reason): array
    {
        if (($admin['role'] ?? 'student') !== 'admin') {
            throw new \RuntimeException('Admin only.');
        }
        $email = trim($email);
        if ($email === '') throw new \RuntimeException('Email is required.');
        if ($days <= 0) throw new \RuntimeException('Ban duration must be > 0 days.');

        $target = $this->users->findByEmail($email);
        if (!$target) throw new \RuntimeException('No user found with that email.');
        if ((int)$target['id'] === (int)$admin['id']) throw new \RuntimeException('You cannot ban yourself.');
        if (($target['role'] ?? 'student') === 'admin') throw new \RuntimeException('You cannot ban another admin.');

        $until = new \DateTimeImmutable('+' . $days . ' days');
        $this->users->setBanById((int)$target['id'], $until, $reason ?: null);

        $target['banned_until'] = $until->format('Y-m-d H:i:s');
        $target['ban_reason']   = $reason ?: null;
        return $target;
    }

    public function unbanUserById(array $admin, int $userId): void
    {
        if (($admin['role'] ?? 'student') !== 'admin') {
            throw new \RuntimeException('Admin only.');
        }
        if ($userId === (int)$admin['id']) throw new \RuntimeException('You cannot unban yourself here.');
        $victim = $this->users->findById($userId);
        if (!$victim) throw new \RuntimeException('User not found.');
        $this->users->clearBanById($userId);
    }

    public function listCurrentlyBanned(): array
    {
        return $this->users->listCurrentlyBanned();
    }
}

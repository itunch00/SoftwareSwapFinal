<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ModerationService;
use App\Middleware\AuthGuard;
use App\Services\GroupService;
use App\Services\ChannelService;
use App\Services\MessageService;
use App\Support\Csrf;
use App\Support\Flash;

final class AdminModerationController
{
    public function __construct(
        private ModerationService $svc,
        private AuthGuard $auth,
        private GroupService $groups,
        private ChannelService $channelSvc,
        private MessageService $messageSvc,
    ) {}

    public function banUser(int $userId, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAdmin($user);

        $days   = (int)($post['days'] ?? 0);
        $reason = trim((string)($post['reason'] ?? ''));

        try {
            if ($days <= 0) throw new \RuntimeException('Ban duration must be > 0 days.');
            $until = new \DateTimeImmutable('+' . $days . ' days');
            $this->svc->banUser((int)$user['id'], $userId, $until, $reason ?: null);
            Flash::success("User banned for {$days} day(s).");
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        header('Location: /users/' . $userId); exit;
    }

    public function unbanUser(int $userId, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAdmin($user);

        try {
            $this->svc->unbanUser((int)$user['id'], $userId);
            Flash::success('User unbanned.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        header('Location: /users/' . $userId); exit;
    }

    public function deleteGroup(int $groupId, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAdmin($user);

        try {
            $this->groups->deleteGroupAsAdmin($groupId);    
            Flash::success('Group deleted.');
            header('Location: /home'); exit;
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            header('Location: /groups'); exit;
        }
    }

    public function deleteChannel(string $groupSlug, string $channelSlug, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAdmin($user);

        try {
            $res = $this->channelSvc->deleteChannelBySlug($groupSlug, $channelSlug, $user);
            Flash::success('Channel deleted.');
            header('Location: /groups/' . $res['group']['slug']);
            exit;
        } catch (\Throwable $e) {
            Flash::error('Failed to delete channel: ' . $e->getMessage());
            header('Location: /groups/' . $groupSlug . '/channels/' . $channelSlug);
            exit;
        }
    }

    public function deleteMessage(string $groupSlug, string $channelSlug, int $messageId, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAdmin($user);

        try {
            $this->messageSvc->deleteMessageById($messageId, $user);
            Flash::success('Message deleted.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }

        header('Location: /groups/' . $groupSlug . '/channels/' . $channelSlug);
        exit;
    }
}

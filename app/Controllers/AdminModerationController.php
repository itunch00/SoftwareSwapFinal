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
use Twig\Environment;

final class AdminModerationController
{
    public function __construct(
        private ModerationService $svc,
        private AuthGuard $auth,
        private GroupService $groups,
        private ChannelService $channelSvc,
        private MessageService $messageSvc,
        private Environment $twig
    ) {}

    // GET /admin/users
    public function usersPage(): void
    {
        $me = $this->auth->mustBeLoggedIn();
        if (($me['role'] ?? 'student') !== 'admin') {
            http_response_code(403);
            echo $this->twig->render('errors/403.twig', ['message' => 'Admin only']); return;
        }

        $banned = $this->svc->listCurrentlyBanned();
        echo $this->twig->render('admin/users.twig', [
            'me'           => $me,
            'banned_users' => $banned,
        ]);
    }

    // POST /admin/users/ban-by-email
    public function banByEmail(array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $me = $this->auth->mustBeLoggedIn();

        try {
            $email  = (string)($post['email'] ?? '');
            $days   = (int)($post['days'] ?? 0);
            $reason = trim((string)($post['reason'] ?? ''));
            $target = $this->svc->banUserByEmail($me, $email, $days, $reason);
            Flash::success('User banned until ' . $target['banned_until'] . '.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        header('Location: /admin/users'); exit;
    }

    // POST /admin/users/{id}/unban
    public function unban(int $userId, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $me = $this->auth->mustBeLoggedIn();

        try {
            $this->svc->unbanUserById($me, $userId);
            Flash::success('User unbanned.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        header('Location: /admin/users'); exit;
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

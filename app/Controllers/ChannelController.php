<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ChannelService;
use App\Services\MessageService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\SlugHelper;
use Twig\Environment;

final class ChannelController
{
    public function __construct(
        private ChannelService $svc,
        private AuthGuard $auth,
        private Environment $twig,
        private MessageService $msgSvc
    ) {}

    /**
     * Create a channel inside a group.
     *
     * @param string $groupSlug Group identifier.
     * @param array  $post      POST payload: name, kind, is_readonly, _csrf.
     * @return void
     *
     * Access: faculty/admin only; actor must be an ACTIVE member of the group.
     * Notes: CSRF-protected. Slug is generated uniquely within the group.
     */
    public function create(string $groupSlug, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAllowedToWrite($user);

        try {
            $payload = [
                'name'        => (string)($post['name'] ?? ''),
                'kind'        => (string)($post['kind'] ?? 'general'),
                'is_readonly' => (int)($post['is_readonly'] ?? 0),
            ];

            $res = $this->svc->createChannel($groupSlug, $payload, $user);

            // If slug differs from name→slug (collision), inform the user.
            if (SlugHelper::fromString($payload['name']) !== $res['slug']) {
                Flash::success("Channel created as “{$res['slug']}” (name adjusted to avoid duplicates).");
            } else {
                Flash::success('Channel created successfully.');
            }

            header('Location: /groups/' . $res['group']['slug'] . '?c=' . $res['slug']);
            // header('Location: /groups/' . $res['group']['slug'] . '/channels/' . $res['slug']);
            exit;

        } catch (\Throwable $e) {
            Flash::error('Failed to create channel: ' . $e->getMessage());
            header('Location: /groups/' . $groupSlug);
            exit;
        }
    }

    /**
     * Show a channel page.
     *
     * @param string     $groupSlug   Group slug.
     * @param string     $channelSlug Channel slug (unique within group).
     * @param array|null $viewer      Current user (or null for guest).
     * @return void                   Renders Twig template or an error page with proper status code.
     *
     * Access: Public groups are viewable by anyone; private groups require membership.
     */
    // public function show(string $groupSlug, string $channelSlug, ?array $viewer): void
    // {
    //     $view = $this->svc->getChannelView($groupSlug, $channelSlug, $viewer);

    //     // Group or channel not found → 404
    //     if (!$view['group'] || !$view['channel']) {
    //         http_response_code(404);
    //         echo $this->twig->render('errors/404.twig');
    //         return;
    //     }

    //     // Private group & not a member → 403
    //     if (!$view['can_view']) {
    //         http_response_code(403);
    //         echo $this->twig->render('errors/403.twig', [
    //             'message' => 'Join this private group to view its channels.',
    //         ]);
    //         return;
    //     }

    //     // Messages for this channel (basic paging could read from query params later)
    //     $messages = $this->msgSvc->getMessagesForChannel($view['group'], $view['channel'], 50);

    //     // Posting permission: must be a member AND (channel writable OR user is faculty/admin)
    //     $canPost = false;
    //     if ($view['is_member']) {
    //         $canPost = ((int)$view['channel']['is_readonly'] === 0)
    //             || ($viewer && in_array($viewer['role'], ['faculty', 'admin'], true));
    //     }

    //     echo $this->twig->render('channels/show.twig', $view + [
    //         'messages' => $messages,
    //         'can_post' => $canPost,
    //     ]);
    // }
}

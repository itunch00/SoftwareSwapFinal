<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ChannelService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use Twig\Environment;

final class ChannelController
{
    public function __construct(
        private ChannelService $svc,
        private AuthGuard $auth,
        private Environment $twig
    ) {}

    /**
     * Create a channel inside a group.
     *
     * @param string $groupSlug Group identifier.
     * @param array  $post      POST payload: name, kind, is_readonly, _csrf.
     * @return void             Redirects to the created channel page or prints error (422).
     *
     * Access: faculty/admin only; actor must be an ACTIVE member of the group.
     * Notes: CSRF-protected. Slug is generated uniquely within the group.
     */
        public function create(string $groupSlug, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();

        try {
            $payload = [
                'name'        => (string)($post['name'] ?? ''),
                'kind'        => (string)($post['kind'] ?? 'general'),
                'is_readonly' => (int)($post['is_readonly'] ?? 0),
            ];
            $res = $this->svc->createChannel($groupSlug, $payload, $user);

            // If slug differs from name->slug (due to collision), let the user know
            if (\App\Support\SlugHelper::fromString($payload['name']) !== $res['slug']) {
                \App\Support\Flash::success("Channel created as “{$res['slug']}” (name adjusted to avoid duplicates).");
            } else {
                \App\Support\Flash::success('Channel created successfully.');
            }

            header('Location: /groups/' . $res['group']['slug'] . '/channels/' . $res['slug']); exit;

        } catch (\Throwable $e) {
            \App\Support\Flash::error('Failed to create channel: ' . $e->getMessage());
            header('Location: /groups/' . $groupSlug); exit;
        }
    }


    /**
     * Show a channel page.
     *
     * @param string     $groupSlug   Group slug.
     * @param string     $channelSlug Channel slug (unique within group).
     * @param array|null $viewer      Current user (or null for guest).
     * @return void                   Renders Twig template or proper error page.
     *
     * Access: Public groups are viewable by anyone; private groups require membership.
     */
    public function show(string $groupSlug, string $channelSlug, ?array $viewer): void
    {
        $view = $this->svc->getChannelView($groupSlug, $channelSlug, $viewer);

        // Group not found → 404
        if (!$view['group']) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        // Channel not found (in this group) → 404
        if (!$view['channel']) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        // Private group & not a member → 403
        if (!$view['can_view']) {
            http_response_code(403);
            echo $this->twig->render('errors/403.twig', [
                'message' => 'Join this private group to view its channels.',
            ]);
            return;
        }

        echo $this->twig->render('channels/show.twig', $view);
    }
}

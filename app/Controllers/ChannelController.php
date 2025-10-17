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

    /** POST /groups/{group_slug}/channels */
    public function create(string $groupSlug, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();

        try {
            $res = $this->svc->createChannel($groupSlug, [
                'name'        => (string)($post['name'] ?? ''),
                'kind'        => (string)($post['kind'] ?? 'general'),
                'is_readonly' => (int)($post['is_readonly'] ?? 0),
            ], $user);

            header('Location: /groups/' . $res['group']['slug'] . '/channels/' . $res['slug']);
            exit;
        } catch (\Throwable $e) {
            http_response_code(422);
            echo $e->getMessage();
        }
    }

    /** GET /groups/{group_slug}/channels/{channel_slug} */
    public function show(string $groupSlug, string $channelSlug, ?array $viewer): void
    {
        $view = $this->svc->getChannelView($groupSlug, $channelSlug, $viewer);

        if (!$view['group'] || !$view['channel']) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        if (!$view['can_view']) {
            http_response_code(403);
            echo "Join this private group to view its channels.";
            return;
        }

        echo $this->twig->render('channels/show.twig', $view);
    }
}

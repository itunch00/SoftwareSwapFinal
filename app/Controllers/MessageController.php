<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MessageService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use App\Support\Flash;

final class MessageController
{
    public function __construct(
        private MessageService $svc,
        private AuthGuard $auth
    ) {}

    /**
     * Handle POST /groups/{g}/channels/{c}/messages to create a new message.
     *
     * @param string $groupSlug   Group slug.
     * @param string $channelSlug Channel slug.
     * @param array  $post        POST payload: _csrf, body.
     * @return void               Redirects back to the channel page with flash feedback.
     */
    public function create(string $groupSlug, string $channelSlug, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();

        try {
            $res = $this->svc->createMessage($groupSlug, $channelSlug, (string)($post['body'] ?? ''), $user);
            Flash::success('Message sent.');
            header('Location: /groups/' . $res['group']['slug'] . '/channels/' . $res['channel']['slug']);
            exit;
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            header('Location: /groups/' . $groupSlug . '/channels/' . $channelSlug);
            exit;
        }
    }
}

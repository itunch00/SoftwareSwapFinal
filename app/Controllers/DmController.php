<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DmService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use App\Support\Flash;
use Twig\Environment;

final class DmController
{
    public function __construct(
        private DmService $svc,
        private AuthGuard $auth,
        private Environment $twig
    ) {}

    /** GET /dms — list conversations */
    public function index(): void
    {
        $user  = $this->auth->mustBeLoggedIn();
        $convs = $this->svc->listForUserFriendly((int)$user['id']);

        echo $this->twig->render('dms/index.twig', [
            'conversations' => $this->svc->listForUserFriendly((int)$user['id']),
            'csrf_token'    => \App\Support\Csrf::token($GLOBALS['c'] ?? null),
        ]);
    }

    // NEW: POST /dms/start — start a DM by email
    public function start(array $post): void
    {
        $user = $this->auth->mustBeLoggedIn();
        Csrf::mustValidate($post['_csrf'] ?? null);
        $email = (string)($post['email'] ?? '');
        try {
            $convId = $this->svc->startByEmail((int)$user['id'], $email);
            Flash::success('Conversation started.');
            header('Location: /dms/' . $convId); exit;
        } catch (\Throwable $e) {
            \App\Support\Flash::error($e->getMessage());
            header('Location: /dms'); exit;
        }
    }


    /** GET /dms/new?user_id=123 — create/open DM then redirect */
    public function new(array $query): void
    {
        $user     = $this->auth->mustBeLoggedIn();
        $targetId = (int)($query['user_id'] ?? 0);

        if ($targetId <= 0) {
            Flash::error('Choose a valid user to message.');
            header('Location: /dms'); exit;
        }
        if ($targetId === (int)$user['id']) {
            Flash::error("You can't message yourself.");
            header('Location: /dms'); exit;
        }

        try {
            // use startOrOpen (not openWithUser)
            $convId = $this->svc->startOrOpen((int)$user['id'], $targetId);
            header('Location: /dms/' . $convId); exit;
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            header('Location: /dms'); exit;
        }
    }

    /** GET /dms/{id} — show thread; supports ?before={message_id} */
    public function show(int $convId): void
    {
        $user   = $this->auth->mustBeLoggedIn();
        $before = isset($_GET['before']) ? (int)$_GET['before'] : null;

        try {
            [$conv, $partner, $messages, $hasMore, $page] =
                $this->svc->getThread($convId, (int)$user['id'], 1, 50);

            echo $this->twig->render('dms/show.twig', [
                'me'         => $user,
                'conv'       => $conv,
                'partner'    => $partner,
                'messages'   => $messages,
                'hasMore'    => $hasMore,
                'page'       => $page,
                'csrf_token' => Csrf::token($GLOBALS['c'] ?? null),
            ]);
        } catch (\Throwable $e) {
            http_response_code(403);
            echo $this->twig->render('errors/403.twig', ['message' => $e->getMessage()]);
        }
    }

    /** POST /dms/{id}/messages — send then redirect back */
    public function send(int $convId, array $post): void
    {
        $user = $this->auth->mustBeLoggedIn();

        Csrf::mustValidate($post['_csrf'] ?? null);
        $body = (string)($post['body'] ?? '');

        try {
            // matches DmService::send(int $convId, int $senderId, string $body): int
            $msgId = $this->svc->send($convId, (int)$user['id'], $body);
            Flash::success('Message sent.');
            header('Location: /dms/' . $convId . '#msg-' . (int)$msgId); exit;
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            header('Location: /dms/' . $convId); exit;
        }
    }
}

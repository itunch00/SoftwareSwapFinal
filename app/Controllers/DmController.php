<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DmService;
use App\Services\MessageService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use Twig\Environment;

final class DmController
{
    public function __construct(
        private DmService $svc,
        private AuthGuard $auth,
        private MessageService $messagesSvc,
        private Environment $twig
    ) {}

    /** GET /dms - list conversations */
    public function index(): void
    {
        $user  = $this->auth->mustBeLoggedIn();
        $convs = $this->svc->listForUserFriendly((int)$user['id']);

        echo $this->twig->render('dms/index.twig', [
            'conversations' => $convs,
            'csrf_token'    => Csrf::token($GLOBALS['c'] ?? null),
        ]);
    }

    // NEW: POST /dms/start - start a DM by email
    public function start(array $post): void
    {
        $user = $this->auth->mustBeLoggedIn();
        Csrf::mustValidate($post['_csrf'] ?? null);
        $email = (string)($post['email'] ?? '');
        try {
            $convId = $this->svc->startByEmail((int)$user['id'], $email);
            header('Location: /dms/' . $convId); exit;
        } catch (\Throwable $e) {
            header('Location: /dms'); exit;
        }
    }


    /** GET /dms/new?user_id=123 — create/open DM then redirect */
    public function new(array $query): void
    {
        $user     = $this->auth->mustBeLoggedIn();
        $targetId = (int)($query['user_id'] ?? 0);

        if ($targetId <= 0) {
            header('Location: /dms'); exit;
        }
        if ($targetId === (int)$user['id']) {
            header('Location: /dms'); exit;
        }

        try {
            // use startOrOpen (not openWithUser)
            $convId = $this->svc->startOrOpen((int)$user['id'], $targetId);
            header('Location: /dms/' . $convId); exit;
        } catch (\Throwable $e) {
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
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAllowedToWrite($user);

        $body = (string)($post['body'] ?? '');

        try {
            // matches DmService::send(int $convId, int $senderId, string $body): int
            $msgId = $this->svc->send($convId, (int)$user['id'], $body);
            header('Location: /dms/' . $convId . '#msg-' . (int)$msgId); exit;
        } catch (\Throwable $e) {
            header('Location: /dms/' . $convId); exit;
        }
    }

     /**
     * Poll endpoint: GET /dms/{id}/poll
     * Returns JSON with latest message in the conversation.
     */
    public function poll(int $convId): void
    {
        $viewer = $this->auth->mustBeLoggedIn();
        $viewerId = (int)$viewer['id'];

        // Make sure conversation belongs to user
        try {
            [$conv] = $this->svc->getThread($convId, $viewerId, 1, 1);
        } catch (\Throwable $e) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation not found']);
            return;
        }

        // Get latest DM
        $latest = $this->messagesSvc->getLatestMessageForConversation($conv);

        header('Content-Type: application/json');
        echo json_encode(['latest_message' => $latest]);
    }
}

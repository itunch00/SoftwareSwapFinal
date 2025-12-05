<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\GroupService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use App\Support\Flash;
use Twig\Environment;
use App\Services\ChannelService;
use App\Services\MessageService;
use App\Repositories\ChannelRepository;

final class GroupController
{
    public function __construct(
        private GroupService $groups,
        private AuthGuard $auth,
        private Environment $twig,
        private ChannelService $channels,
        private MessageService $messagesSvc,
        private ChannelRepository $channelRepo,
    ) {}

    public function create(array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();

        $name = trim((string)($post['name'] ?? ''));
        if ($name === '') {
            Flash::error('Group name is required.');
            header('Location: /home'); exit;
        }

        try {
            $res = $this->groups->createGroup([
                'name'        => $name,
                'description' => trim((string)($post['description'] ?? '')),
                'visibility'  => in_array(($post['visibility'] ?? 'public'), ['public','private'], true)
                                 ? $post['visibility'] : 'public',
            ], (int)$user['id']);

            Flash::success('Group created successfully.');
            header('Location: /groups/' . $res['slug']); exit;

        } catch (\Throwable $e) {
            Flash::error('Failed to create group: ' . $e->getMessage());
            header('Location: /home'); exit;
        }
    }

    public function show(string $slug, ?array $viewer): void
    {
        $view = $this->groups->getGroupView($slug, $viewer);

        if (!$view['group']) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        $cSlug = isset($_GET['c']) ? trim((string)$_GET['c']) : null;
        $selected = null;

        if ($cSlug) {
            $selected = $this->channelRepo->findByGroupAndSlug($view['group']['id'], $cSlug);
        }
        if (!$selected && !empty($view['channels'])) {
            $selected = $view['channels'][0];
        }

        $messages   = [];
        $canPost    = false;
        $isMember   = (bool)($view['is_member'] ?? false);
        $isAdmin    = ($viewer && ($viewer['role'] ?? '') === 'admin');
        $latestMessage = null;

        if ($selected) {
            $messages = $this->messagesSvc->getMessagesForChannel($view['group'], $selected, 50);
            $canPost  = $isAdmin || $isMember || (int)$selected['is_readonly'] === 0;

            if (!empty($messages)) {
                $latestMessage = end($messages);
            }
        }

        echo $this->twig->render('groups/show.twig', $view + [
            'viewer'           => $viewer,
            'selected_channel' => $selected,
            'messages'         => $messages,
            'can_post'         => $canPost,
            'latest_message'   => $latestMessage,
        ]);
    }

    /**
     * Poll endpoint: GET /groups/{slug}/poll
     * Returns JSON with the latest message id for the group.
     */
    public function poll(string $slug): void
    {
        $viewer = $this->auth->mustBeLoggedIn();
        $view   = $this->groups->getGroupView($slug, $viewer);

        if (!$view['group']) {
            http_response_code(404);
            echo json_encode(['error' => 'Group not found']);
            return;
        }

        // Fetch the latest message row, not just the ID
        $latest = $this->messagesSvc->getLatestMessageForGroup($view['group']);

        header('Content-Type: application/json');
        echo json_encode(['latest_message' => $latest]);
    }

}

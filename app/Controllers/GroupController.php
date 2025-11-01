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

    /**
     * Handle POST /groups to create a group and redirect to its page.
     *
     * @param array $post Expected: _csrf, name, description?, visibility?
     * @return void
     */
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

    /**
     * Handle GET /groups/{slug} to show a group.
     *
     * @param string $slug
     * @param array|null $viewer
     * @return void
     */
    public function show(string $slug, ?array $viewer): void
    {
        // Base group view (group, channels, is_member, can_view, etc.)
        $view = $this->groups->getGroupView($slug, $viewer);

        if (!$view['group']) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        // --- NEW: selected channel via ?c=slug (fallback to first channel) ---
        $cSlug = isset($_GET['c']) ? trim((string)$_GET['c']) : null;
        $selected = null;

        if ($cSlug) {
            $selected = $this->channelRepo->findByGroupAndSlug($view['group']['id'], $cSlug);
        }
        if (!$selected && !empty($view['channels'])) {
            $selected = $view['channels'][0]; // optional auto-select
        }

        // --- Load messages + posting permission when selected ---
        $messages   = [];
        $canPost    = false;
        $isMember   = (bool)($view['is_member'] ?? false);
        $isAdmin    = ($viewer && ($viewer['role'] ?? '') === 'admin');

        if ($selected) {
            // reuse your existing paging size or change as you like
            $messages = $this->messagesSvc->getMessagesForChannel($view['group'], $selected, 50);
            $canPost  = $isAdmin || $isMember || (int)$selected['is_readonly'] === 0;
        }

        echo $this->twig->render('groups/show.twig', $view + [
            'viewer'           => $viewer,
            'selected_channel' => $selected,
            'messages'         => $messages,
            'can_post'         => $canPost,
        ]);
    }

}

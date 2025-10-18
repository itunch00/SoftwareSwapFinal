<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\GroupService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use App\Support\Flash;
use Twig\Environment;

final class GroupController
{
    public function __construct(
        private GroupService $groups,
        private AuthGuard $auth,
        private Environment $twig
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

    public function show(string $slug, ?array $viewer): void
    {
        $view = $this->groups->getGroupView($slug, $viewer ? (int)$viewer['id'] : null);

        if (!$view['group']) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        echo $this->twig->render('groups/show.twig', $view + ['viewer' => $viewer]);
    }
}

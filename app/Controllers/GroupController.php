<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\GroupService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use Twig\Environment;

final class GroupController
{
    public function __construct(
        private GroupService $groups,
        private AuthGuard $auth,
        private Environment $twig
    ) {}

    public function create(array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);

        $user = $this->auth->mustBeLoggedIn();
        $payload = [
            'name'        => trim((string)($post['name'] ?? '')),
            'description' => trim((string)($post['description'] ?? '')),
            'visibility'  => in_array(($post['visibility'] ?? 'public'), ['public','private'], true)
                             ? $post['visibility'] : 'public',
        ];
        if ($payload['name'] === '') {
            http_response_code(422);
            echo "Group name required";
            return;
        }

        $res = $this->groups->createGroup($payload, (int)$user['id']);
        header("Location: /groups/{$res['slug']}");
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

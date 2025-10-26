<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MembershipService;
use App\Middleware\AuthGuard;
use App\Support\Csrf;

final class MembershipController
{
    public function __construct(
        private MembershipService $svc,
        private AuthGuard $auth
    ) {}

    public function join(string $slug, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAllowedToWrite($user);

        if (!$this->svc->join($slug, (int)$user['id'])) {
            http_response_code(404);
            echo "Group not found";
            return;
        }
        header("Location: /groups/{$slug}");
    }

    public function leave(string $slug, array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();
        $this->auth->mustBeAllowedToWrite($user);

        if (!$this->svc->leave($slug, (int)$user['id'])) {
            http_response_code(404);
            echo "Group not found";
            return;
        }
        header("Location: /home");
    }
}

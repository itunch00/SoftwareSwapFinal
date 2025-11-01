<?php
declare(strict_types=1);

namespace App\Controllers;

use Twig\Environment;
use App\Middleware\AuthGuard;

final class LandingController
{
    public function __construct(
        private Environment $twig,
        private AuthGuard $auth
    ) {}

    public function show(): void
    {
        $viewer = $this->auth->userOrNull();
        if ($viewer) {
            header('Location: /home'); exit;
        }
        echo $this->twig->render('landing.twig', ['brand_href' => '/login']);
    }
}

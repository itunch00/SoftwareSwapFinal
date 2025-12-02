<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ProfileService;
use App\Repositories\UserRepository;
use App\Middleware\AuthGuard;
use App\Support\Csrf;
use App\Support\Flash;
use Twig\Environment;

final class ProfileController
{
    public function __construct(
        private ProfileService $svc,
        private AuthGuard $auth,
        private Environment $twig
    ) {}

    /**
     * Show the logged-in user's profile page with edit form.
     *
     * @return void Renders Twig template.
     */
    public function me(): void
    {
        $user = $this->auth->mustBeLoggedIn();
        $data = $this->svc->getMeView((int)$user['id']);

        echo $this->twig->render('profile/me.twig', [
            'user'       => $data['user'],
            'profile'    => $data['profile'],
            'csrf_token' => \App\Support\Csrf::token($GLOBALS['c'] ?? null), // optional; we expose csrf_token() in Twig anyway
        ]);
    }

    /**
     * Handle profile updates for current user.
     *
     * @param array $post Expected: _csrf, display_name?, bio?, website?, github?, linkedin?, pronouns?, timezone?
     * @return void Redirects back with a flash message.
     */
    public function update(array $post): void
    {
        Csrf::mustValidate($post['_csrf'] ?? null);
        $user = $this->auth->mustBeLoggedIn();

        try {
            $this->svc->updateMe((int)$user['id'], $post);
            Flash::success('Profile updated.');
        } catch (\Throwable $e) {
            Flash::error('Failed to update profile: ' . $e->getMessage());
        }

        header('Location: /profile');
        exit;
    }

    public function show(int $id)
    {
        // Reuse the service you already have
        $view = $this->svc->getMeView($id);

        if (!$view || !$view['user']) {
            Flash::error("User not found.");
            return redirect('/home');
        }

        echo $this->twig->render('profile/show.twig', [
            'user'    => $view['user'],
            'profile' => $view['profile'],
        ]);
    }
}
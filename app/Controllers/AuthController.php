<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Container;
use App\Support\Csrf;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\Flash;

class AuthController
{
    private AuthService $auth;
    private UserRepository $userRepo;

    public function __construct(private Container $c)
    {
        $this->auth = new AuthService(new UserRepository($c->db));
        $this->userRepo = new UserRepository($c->db);
    }

    public function showLogin(): void
    {
        if (isset($_SESSION['user'])) { header('Location:/home'); exit; }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $this->c->twig->render('auth/login.twig', [
            'csrf_token' => Csrf::token($this->c),
        ]);
    }

    public function showSignup(): void
    {
        if (isset($_SESSION['user'])) { header('Location:/home'); exit; }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $this->c->twig->render('auth/signup.twig', [
            'csrf_token' => Csrf::token($this->c),
        ]);
    }

    public function login(): void
    {
        Csrf::mustValidate($_POST['_csrf'] ?? null);

        $email = (string)($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');

        try {
            $user = $this->auth->login($email, $pass);

            // Block banned users BEFORE creating session
            if (!empty($user['banned_until'])) {
                $until = new \DateTimeImmutable($user['banned_until']);
                $now   = new \DateTimeImmutable('now');
                if ($until > $now) {
                    Flash::error(
                        'Your account is banned until ' . $user['banned_until'] .
                        (!empty($user['ban_reason']) ? ' - Reason: ' . $user['ban_reason'] : '')
                    );
                    header('Location: /login'); exit;
                }

                // auto-clear expired bans
                if ($until <= $now) {
                    $this->userRepo->clearBanById((int)$user['id']);
                    $user['banned_until'] = null;
                    $user['ban_reason']   = null;
                }
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => (int)$user['id'],
                'email' => $user['email'],
                'name'  => $user['display_name'],
                'role'  => $user['role'],
            ];

            header('Location: /home'); exit;

        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            header('Location: /login'); exit;
        }
    }

    public function signup(): void
    {
        Csrf::mustValidate($_POST['_csrf'] ?? null);

        $email = (string)($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        $name  = trim((string)($_POST['display_name'] ?? ''));
        $role  = in_array($_POST['role'] ?? 'student', ['student','faculty','admin'], true)
            ? $_POST['role'] : 'student';

        try {
            $this->auth->signup($email, $pass, $name, $role);
            Flash::success('Account created. Please log in.');
            header('Location: /login'); exit;
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            header('Location: /signup'); exit;
        }
    }

    public function logout(): void
    {
        Csrf::mustValidate($_POST['_csrf'] ?? null); 

        unset($_SESSION['user']);
        session_regenerate_id(true);
        header('Location: /login'); exit;
    }
}

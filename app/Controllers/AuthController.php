<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Container;
use App\Support\Csrf;
use App\Repositories\UserRepository;
use App\Services\AuthService;

class AuthController
{
    private AuthService $auth;

    public function __construct(private Container $c)
    {
        $this->auth = new AuthService(new UserRepository($c->db));
    }

    public function showLogin(): void
    {
        echo $this->c->twig->render('auth/login.twig', [
            'error'      => $_SESSION['flash_error'] ?? null,
            'csrf_token' => \App\Support\Csrf::token($this->c),
        ]);
        unset($_SESSION['flash_error']);
    }

    public function login(): void
    {
        \App\Support\Csrf::check($this->c, $_POST['_token'] ?? null);

        $email = (string)($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');

        try {
            $user = $this->auth->login($email, $pass);
            $_SESSION['user'] = [
                'id'    => (int)$user['id'],
                'email' => $user['email'],
                'name'  => $user['display_name'],
                'role'  => $user['role'],
            ];
            session_regenerate_id(true);
            header('Location: /home'); exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /login'); exit;
        }
    }

    public function showSignup(): void
    {
        echo $this->c->twig->render('auth/signup.twig', [
            'error'      => $_SESSION['flash_error'] ?? null,
            'csrf_token' => \App\Support\Csrf::token($this->c),
        ]);
        unset($_SESSION['flash_error']);
    }

    public function signup(): void
    {
        \App\Support\Csrf::check($this->c, $_POST['_token'] ?? null);

        $email = (string)($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        $name  = trim((string)($_POST['display_name'] ?? ''));
        $role  = in_array($_POST['role'] ?? 'student', ['student','faculty','admin'], true)
               ? $_POST['role'] : 'student';

        try {
            $this->auth->signup($email, $pass, $name, $role);
            $_SESSION['flash_error'] = null;
            header('Location: /login'); exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /signup'); exit;
        }
    }

    public function logout(): void
    {
        \App\Support\Csrf::check($this->c, $_POST['_token'] ?? null);

        unset($_SESSION['user']);
        session_regenerate_id(true);
        header('Location: /login'); exit;
    }
}

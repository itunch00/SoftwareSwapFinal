<?php
declare(strict_types=1);

namespace App\Middleware;

final class AuthGuard
{
    public function mustBeLoggedIn(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            header('Location: /login'); exit;
        }
        return $_SESSION['user'];
    }

    public function userOrNull(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION['user'] ?? null;
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = [];
        session_destroy();
        header('Location: /login'); exit;
    }
}

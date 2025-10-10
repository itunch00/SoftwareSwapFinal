<?php
declare(strict_types=1);

namespace App\Middleware;

final class AuthGuard
{
    /**
     * Ensure a user is logged in, or redirect to /login.
     * Returns the user array if logged in.
     */
    public function mustBeLoggedIn(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        return $_SESSION['user'];
    }

    /**
     * Returns the logged-in user, or null if guest.
     */
    public function userOrNull(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['user'] ?? null;
    }

    /**
     * Log the user out (destroy session and redirect to login).
     */
    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }
}

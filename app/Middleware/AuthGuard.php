<?php
declare(strict_types=1);

namespace App\Middleware;
use App\Support\Flash;

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

    public function mustBeAdmin(array $user): void
    {
        if (($user['role'] ?? 'student') !== 'admin') {
            http_response_code(403);
            throw new \RuntimeException('Admin privileges required.');
        }
    }

    private function isBanned(array $user): bool
    {
        if (empty($user['banned_until'])) return false;
        return (new \DateTimeImmutable($user['banned_until'])) > new \DateTimeImmutable('now');
    }

    /**
     * Call this right after mustBeLoggedIn() in any POST handler.
     * Redirects to /home with a flash error if the user is banned.
     */
    public function mustBeAllowedToWrite(array $user): void
    {
        if ($this->isBanned($user)) {
            Flash::error(
                'You are banned until ' . $user['banned_until']
                . (!empty($user['ban_reason']) ? ' — Reason: ' . $user['ban_reason'] : '')
            );
            header('Location: /home'); exit;
        }
    }
}

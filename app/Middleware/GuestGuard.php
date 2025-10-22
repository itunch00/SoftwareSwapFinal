<?php
declare(strict_types=1);

namespace App\Middleware;

final class GuestGuard
{
    /**
     * Redirect authenticated users to /home.
     */
    public function mustBeGuest(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            header('Location: /home'); exit;
        }
    }
}

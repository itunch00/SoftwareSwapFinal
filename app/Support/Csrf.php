<?php
declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    /**
     * Generate or reuse a CSRF token for the current session.
     * Stores it in $_SESSION['_csrf'] and returns it for embedding in forms.
     */
    public static function token(Container $c): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Generate a deterministic HMAC per session using APP_KEY
        $token = hash_hmac('sha256', session_id(), $c->appKey);
        $_SESSION['_csrf'] = $token;
        return $token;
    }

    /**
     * Validate a CSRF token from a submitted form.
     * Exits with 403 on mismatch.
     */
    public static function mustValidate(?string $posted): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $expected = $_SESSION['_csrf'] ?? null;

        if (!$expected || !$posted || !hash_equals($expected, $posted)) {
            http_response_code(403);
            exit('CSRF token mismatch');
        }
    }

    /**
     * Convenience helper for Twig templates to insert CSRF tokens easily.
     */
    public static function exposeToTwig(\Twig\Environment $twig, Container $c): void
    {
        $twig->addFunction(new \Twig\TwigFunction('csrf_token', function () use ($c) {
            return self::token($c);
        }));
    }
}

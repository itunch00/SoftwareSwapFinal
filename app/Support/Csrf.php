<?php
declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(Container $c): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['_csrf'])) {
            $raw = random_bytes(32);
            $_SESSION['_csrf'] = hash_hmac('sha256', $raw, $c->appKey);
        }
        return $_SESSION['_csrf'];
    }

    public static function mustValidate(?string $posted): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $expected = $_SESSION['_csrf'] ?? null;
        if (!$expected || !$posted || !hash_equals($expected, $posted)) {
            http_response_code(403);
            exit('CSRF token mismatch');
        }
    }

    // Back-compat shim (if older code calls Csrf::check($c, ...))
    public static function check($unusedContainer, ?string $posted): void
    {
        self::mustValidate($posted);
    }

    public static function exposeToTwig(\Twig\Environment $twig, Container $c): void
    {
        $twig->addFunction(new \Twig\TwigFunction('csrf_token', function () use ($c) {
            return self::token($c);
        }));
    }
}

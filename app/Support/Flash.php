<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Tiny flash-message helper using session storage.
 * - Use Flash::success()/error() to set a message for the next request.
 * - Use Flash::consumeAll() once per render to retrieve-and-clear messages.
 * - Expose a Twig function flash() that returns and consumes all flashes.
 */
final class Flash
{
    /**
     * Queue a success message to be shown on the next request.
     *
     * @param string $message Human-friendly success note.
     * @return void
     */
    public static function success(string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['_flash']['success'][] = $message;
    }

    /**
     * Queue an error message to be shown on the next request.
     *
     * @param string $message Human-friendly error note.
     * @return void
     */
    public static function error(string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['_flash']['error'][] = $message;
    }

    /**
     * Pop all flash messages and clear them from the session.
     *
     * @return array{success?: string[], error?: string[]}
     */
    public static function consumeAll(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $fl = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $fl;
    }

    /**
     * Expose a Twig function `flash()` that consumes and returns all flashes.
     * Usage in Twig:
     *   {% set f = flash() %}
     *   {% for msg in f.success ?? [] %}<div class="alert success">{{ msg }}</div>{% endfor %}
     *
     * @param \Twig\Environment $twig Twig environment to extend.
     * @return void
     */
    public static function exposeToTwig(\Twig\Environment $twig): void
    {
        $twig->addFunction(new \Twig\TwigFunction('flash', function (): array {
            return self::consumeAll();
        }));
    }
}

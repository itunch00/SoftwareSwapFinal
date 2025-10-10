<?php
declare(strict_types=1);

use App\Support\Container;
use App\Controllers\AuthController;
use App\Controllers\GroupController;
use App\Controllers\MembershipController;
use App\Support\Csrf;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$isDebug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');

/** ---------- Small HTTP helpers ---------- */
function send(string $html, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}
function redirect(string $path, int $code = 302): void {
    if (!str_starts_with($path, '/')) {
        $path = '/' . ltrim($path, '/');
    }
    header('Location: ' . $path, true, $code);
    exit;
}
function notFound(): void { send('Not Found', 404); }
function methodNotAllowed(): void { send('Method Not Allowed', 405); }

try {
    // App container (sets up PDO, Twig, sessions, APP_KEY, etc.)
    $c = new Container();

    // Basic routing params (single parse)
    $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Controllers
    // Assumes your Container exposes: twig, authGuard, groupService, membershipService
    $authController        = new AuthController($c);
    $groupController       = new GroupController($c->groupService, $c->authGuard, $c->twig);
    $membershipController  = new MembershipController($c->membershipService, $c->authGuard);

    /** ---------- Routes ---------- */

    // Root -> login
    if ($uri === '/' && $method === 'GET') {
        redirect('/login');
    }

    // Login
    if ($uri === '/login') {
        if ($method === 'GET')  { $authController->showLogin(); exit; }
        if ($method === 'POST') { $authController->login();     exit; }
        methodNotAllowed();
    }

    // Signup
    if ($uri === '/signup') {
        if ($method === 'GET')  { $authController->showSignup(); exit; }
        if ($method === 'POST') { $authController->signup();     exit; }
        methodNotAllowed();
    }

    // Logout
    if ($uri === '/logout') {
        if ($method === 'POST') { $authController->logout(); exit; }
        methodNotAllowed();
    }

    // Protected example: /home
    if ($uri === '/home' && $method === 'GET') {
        $c->authGuard->mustBeLoggedIn();
        echo $c->twig->render('home.twig', [
            'user'       => $_SESSION['user'],
            'csrf_token' => Csrf::token($c),
        ]);
        exit;
    }

    // Groups: create
    if ($uri === '/groups' && $method === 'POST') {
        $groupController->create($_POST);
        exit;
    }

    // Groups: show (/groups/{slug})
    if ($method === 'GET' && preg_match('#^/groups/([a-z0-9\-]+)$#', $uri, $m)) {
        $viewer = $c->authGuard->userOrNull();
        $groupController->show($m[1], $viewer);
        exit;
    }

    // Membership: join (/groups/{slug}/join)
    if ($method === 'POST' && preg_match('#^/groups/([a-z0-9\-]+)/join$#', $uri, $m)) {
        $membershipController->join($m[1], $_POST);
        exit;
    }

    // Membership: leave (/groups/{slug}/leave)
    if ($method === 'POST' && preg_match('#^/groups/([a-z0-9\-]+)/leave$#', $uri, $m)) {
        $membershipController->leave($m[1], $_POST);
        exit;
    }

    // Fallback
    notFound();

} catch (Throwable $e) {
    $errorMsg = sprintf("[%s] %s in %s:%d\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($errorMsg);

    $userMsg = $isDebug
        ? '<pre>' . htmlspecialchars($errorMsg, ENT_QUOTES) . '</pre>'
        : 'Something went wrong.';

    send($userMsg, 500);
}

<?php
declare(strict_types=1);

use App\Support\Container;
use App\Controllers\AuthController;
use App\Controllers\GroupController;
use App\Controllers\MembershipController;
use App\Controllers\ChannelController;
use App\Controllers\MessageController;
use App\Controllers\ProfileController;  
use App\Controllers\DmController;
use App\Controllers\AdminModerationController;
use Twig\Environment;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$isDebug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');

function send(string $html, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo $html; exit;
}
function redirect(string $path, int $code = 302): void {
    if (!str_starts_with($path, '/')) $path = '/' . ltrim($path, '/');
    header('Location: ' . $path, true, $code); exit;
}
function notFound(Environment $twig): void { 
    http_response_code(404);
    echo $twig->render('errors/404.twig');
    return;
 }
function methodNotAllowed(): void { send('Method Not Allowed', 405); }

try {
    $c = new Container();

    $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $authController       = new AuthController($c);
    $groupController      = new GroupController($c->groupService, $c->authGuard, $c->twig, $c->channelService, $c->messageService, $c->channelRepository);
    $membershipController = new MembershipController($c->membershipService, $c->authGuard);
    $channelController = new ChannelController($c->channelService, $c->authGuard, $c->twig, $c->messageService);
    $messageController = new MessageController($c->messageService, $c->authGuard);
    $profile = new ProfileController($c->profileService, $c->authGuard, $c->twig);
    $dm = new DmController($c->dmService, $c->authGuard, $c->twig);
    $admin = new AdminModerationController($c->moderationService, $c->authGuard, $c->groupService, $c->channelService, $c->messageService, $c->twig);
    $twig = $c->twig;

    // root -> login
    if ($uri === '/' && $method === 'GET') {
        $viewer = $c->authGuard->userOrNull();
        if ($viewer) {
            redirect('/home'); // already signed in
        }
        echo $c->twig->render('landing.twig', [
            'brand_href' => '/login',   // clicking brand can also take to login if you like
        ]);
        exit;
    }
    // if ($uri === '/' && $method === 'GET') { redirect('/login'); }

    // login
    if ($uri === '/login') {
        $c->guestGuard->mustBeGuest();
        if ($method === 'GET')  { $authController->showLogin(); exit; }
        if ($method === 'POST') { $authController->login();     exit; }
        methodNotAllowed();
    }

    // signup
    if ($uri === '/signup') {
        $c->guestGuard->mustBeGuest();
        if ($method === 'GET')  { $authController->showSignup(); exit; }
        if ($method === 'POST') { $authController->signup();     exit; }
        methodNotAllowed();
    }

    // logout
    if ($uri === '/logout') {
        if ($method === 'POST') { $authController->logout(); exit; }
        methodNotAllowed();
    }

    // protected: /home
    if ($uri === '/home' && $method === 'GET') {
        $user = $c->authGuard->mustBeLoggedIn();

        // NEW: for admins, send a single merged list
        if (($user['role'] ?? 'student') === 'admin') {
            $all = $c->groupService->listAllForAdmin();
            echo $c->twig->render('home.twig', [
                'user'        => $user,
                'csrf_token'  => \App\Support\Csrf::token($c),
                'admin_groups'=> $all,           // <-- single list for admins
            ]);
            exit;
        }

        // existing behavior for non-admins
        $yourGroups   = $c->groupService->groupsForUser((int)$user['id']);
        $publicGroups = $c->groupService->discoverablePublicGroupsForUser((int)$user['id']);

        echo $c->twig->render('home.twig', [
            'user'          => $user,
            'csrf_token'    => \App\Support\Csrf::token($c),
            'your_groups'   => $yourGroups,
            'public_groups' => $publicGroups,
        ]);
        exit;
    }

    // groups
    if ($uri === '/groups' && $method === 'POST') {
        $groupController->create($_POST); exit;
    }
    if ($method === 'GET' && preg_match('#^/groups/([a-z0-9\-]+)$#', $uri, $m)) {
        $viewer = $c->authGuard->userOrNull();
        $groupController->show($m[1], $viewer); exit;
    }

    // memberships
    if ($method === 'POST' && preg_match('#^/groups/([a-z0-9\-]+)/join$#', $uri, $m)) {
        $membershipController->join($m[1], $_POST); exit;
    }
    if ($method === 'POST' && preg_match('#^/groups/([a-z0-9\-]+)/leave$#', $uri, $m)) {
        $membershipController->leave($m[1], $_POST); exit;
    }

    // CREATE channel
    if ($method === 'POST' && preg_match('#^/groups/([a-z0-9\-]+)/channels$#', $uri, $m)) {
        $channelController->create($m[1], $_POST);
        exit;
    }

    // SHOW channel (legacy) -> redirect to hub with ?c=
    if ($method === 'GET' && preg_match('#^/groups/([a-z0-9\-]+)/channels/([a-z0-9\-]+)$#', $uri, $m)) {
        redirect("/groups/{$m[1]}?c={$m[2]}");
    }

    // SHOW channel
    // if ($method === 'GET' && preg_match('#^/groups/([a-z0-9\-]+)/channels/([a-z0-9\-]+)$#', $uri, $m)) {
    //     $viewer = $c->authGuard->userOrNull();
    //     $channelController->show($m[1], $m[2], $viewer);
    //     exit;
    // }

    // POST /groups/{group}/channels/{channel}/messages
    if ($method === 'POST' && preg_match('#^/groups/([a-z0-9\-]+)/channels/([a-z0-9\-]+)/messages$#', $uri, $m)) {
        $messageController->create($m[1], $m[2], $_POST);
        exit;
    }

    // GET /profile
    if ($uri === '/profile' && $method === 'GET') {
        $profile->me(); exit;
    }

    // POST /profile
    if ($uri === '/profile' && $method === 'POST') {
        $profile->update($_POST); exit;
    }

    // GET /dms
    if ($uri === '/dms' && $method === 'GET') { $dm->index(); exit; }

    // GET /dms/new?user_id=123
    if ($uri === '/dms/new' && $method === 'GET') { $dm->new($_GET); exit; }

    // GET /dms/{id}
    if ($method === 'GET' && preg_match('#^/dms/(\d+)$#', $uri, $m)) {
        $dm->show((int)$m[1]); exit;
    }

    // POST /dms/{id}/messages
    if ($method === 'POST' && preg_match('#^/dms/(\d+)/messages$#', $uri, $m)) {
        $dm->send((int)$m[1], $_POST); exit;
    }

    // POST /dms/start
    if ($method === 'POST' && $uri === '/dms/start') {
        $dm->start($_POST);
        exit;
    }

    // DELETE message (admin)
    if ($method === 'POST' &&
        preg_match('#^/groups/([a-z0-9\-]+)/channels/([a-z0-9\-]+)/messages/(\d+)/delete$#', $uri, $m)) {
        $admin->deleteMessage($m[1], $m[2], (int)$m[3], $_POST); exit;
    }

    // DELETE channel (admin)
    if ($method === 'POST' &&
        preg_match('#^/groups/([a-z0-9\-]+)/channels/([a-z0-9\-]+)/delete$#', $uri, $m)) {
        $admin->deleteChannel($m[1], $m[2], $_POST); exit;
    }

    // Admin user moderation UI
    if ($method === 'GET' && $uri === '/admin/users') {
        $admin->usersPage(); exit;
    }

    // Ban by email
    if ($method === 'POST' && $uri === '/admin/users/ban-by-email') {
        $admin->banByEmail($_POST); exit;
    }

    // Unban by id
    if ($method === 'POST' && preg_match('#^/admin/users/(\d+)/unban$#', $uri, $m)) {
        $admin->unban((int)$m[1], $_POST); exit;
    }

    // POST /admin/groups/{id}/delete
    if ($method === 'POST' && preg_match('#^/admin/groups/(\d+)/delete$#', $uri, $m)) {
        $admin->deleteGroup((int)$m[1], $_POST); exit;
    }

    notFound($twig);
} catch (Throwable $e) {
    $errorMsg = sprintf("[%s] %s in %s:%d\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($errorMsg);
    $userMsg = $isDebug ? '<pre>' . htmlspecialchars($errorMsg, ENT_QUOTES) . '</pre>' : 'Something went wrong.';
    send($userMsg, 500);
}

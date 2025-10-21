<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

use App\Middleware\AuthGuard;

use App\Services\GroupService;
use App\Services\MembershipService;
use App\Services\ChannelService;
use App\Services\MessageService;

use App\Repositories\GroupRepository;
use App\Repositories\ChannelRepository;
use App\Repositories\GroupMembershipRepository;
use App\Repositories\MessageRepository;

final class Container
{
    public PDO $db;
    public Environment $twig;
    public string $appKey;

    public AuthGuard $authGuard;
    public GroupService $groupService;
    public MembershipService $membershipService;
    public ChannelService $channelService;
    public MessageService $messageService;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'secure'   => false, // set true behind HTTPS
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $this->appKey = (string)($_ENV['APP_KEY'] ?? '');
        if ($this->appKey === '') {
            throw new \RuntimeException('APP_KEY missing in .env');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? '127.0.0.1',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_NAME'] ?? 'uafs_social'
        );

        $this->db = new PDO(
            $dsn,
            $_ENV['DB_USER'] ?? 'uafs_app',
            $_ENV['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        $loader = new FilesystemLoader(__DIR__ . '/../Views');
        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => (($_ENV['APP_DEBUG'] ?? 'false') === 'true'),
        ]);
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $this->twig->addExtension(new DebugExtension());
        }

        // Current path for active nav & breadcrumbs
        $this->twig->addGlobal('current_path', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        // Tiny helper to highlight active nav items by prefix
        $this->twig->addFunction(new \Twig\TwigFunction('is_active', function (string $prefix): bool {
            $cp = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            return str_starts_with($cp, $prefix);
        }));

        // Make csrf_token() available in Twig
        \App\Support\Csrf::exposeToTwig($this->twig, $this);

        // 🔹 Expose flash() to Twig
        \App\Support\Flash::exposeToTwig($this->twig);

        // Middleware
        $this->authGuard = new AuthGuard();

        // Repos
        $groupRepo      = new GroupRepository($this->db);
        $channelRepo    = new ChannelRepository($this->db);
        $membershipRepo = new GroupMembershipRepository($this->db);
        $messageRepo = new MessageRepository($this->db);

        // Services
        $this->groupService      = new GroupService($groupRepo, $channelRepo, $membershipRepo);
        $this->membershipService = new MembershipService($groupRepo, $membershipRepo);
        $this->channelService = new ChannelService($groupRepo, $channelRepo, $membershipRepo);
        $this->messageService = new MessageService($groupRepo, $channelRepo, $membershipRepo, $messageRepo);

    }
}

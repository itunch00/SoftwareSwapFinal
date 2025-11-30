<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

use App\Middleware\AuthGuard;
use App\Middleware\GuestGuard;

use App\Services\GroupService;
use App\Services\MembershipService;
use App\Services\ChannelService;
use App\Services\MessageService;
use App\Services\ProfileService;
use App\Services\DmService;
use App\Services\ModerationService;
use App\Services\NotificationService;

use App\Repositories\GroupRepository;
use App\Repositories\ChannelRepository;
use App\Repositories\GroupMembershipRepository;
use App\Repositories\MessageRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\DmConversationRepository;
use App\Repositories\DmMessageRepository;
use App\Repositories\ModerationActionRepository;

final class Container
{
    public PDO $db;
    public Environment $twig;
    public string $appKey;

    public AuthGuard $authGuard;
    public GuestGuard $guestGuard;

    public GroupService $groupService;
    public MembershipService $membershipService;
    public ChannelService $channelService;
    public MessageService $messageService;
    public ProfileService $profileService;
    public DmService $dmService;
    public ModerationService $moderationService;
    public ChannelRepository $channelRepository;
    public NotificationService $notificationService;

    public function __construct()
    {
        // --- Session bootstrap ---
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'secure'   => false, // set true when behind HTTPS
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        // --- App key ---
        $this->appKey = (string)($_ENV['APP_KEY'] ?? '');
        if ($this->appKey === '') {
            throw new \RuntimeException('APP_KEY missing in .env');
        }

        // --- Database (PDO) ---
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

        // --- Twig ---
        $loader = new FilesystemLoader(__DIR__ . '/../Views');
        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => (($_ENV['APP_DEBUG'] ?? 'false') === 'true'),
        ]);

        //gets notifications
        $this->twig->addFunction(new \Twig\TwigFunction('get_notifications', function($userId) {
            if (!$userId) return [];
            $stmt = $this->db->prepare("
                SELECT n.id, n.name, n.time_sent
                FROM notifications n
                JOIN notification_assignments na ON na.notif_id = n.id
                WHERE na.user_id = :uid
                ORDER BY n.time_sent DESC
            ");
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }));

        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $this->twig->addExtension(new DebugExtension());
        }

        // 🔹 Make the current user available everywhere in Twig (for navbar, etc.)
        $this->twig->addGlobal('app', [
            'user' => $_SESSION['user'] ?? null,
        ]);

        // 🔹 Current path & simple "active" helper for nav highlighting
        $this->twig->addGlobal('current_path', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $this->twig->addFunction(new \Twig\TwigFunction('is_active', function (string $prefix): bool {
            $cp = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            return str_starts_with($cp, $prefix);
        }));

        // 🔹 Expose csrf_token() and flash() to Twig
        \App\Support\Csrf::exposeToTwig($this->twig, $this);
        \App\Support\Flash::exposeToTwig($this->twig);

        // --- Middleware ---
        $this->authGuard = new AuthGuard();
        $this->guestGuard = new GuestGuard();
        
        // --- Repositories ---
        $groupRepo      = new GroupRepository($this->db);
        $channelRepo    = new ChannelRepository($this->db);
        $membershipRepo = new GroupMembershipRepository($this->db);
        $messageRepo    = new MessageRepository($this->db);
        $profileRepo    = new UserProfileRepository($this->db);
        $userRepo       = new UserRepository($this->db);
        $dmConvRepo = new DmConversationRepository($this->db);
        $dmMsgRepo  = new DmMessageRepository($this->db);
        $moderationActionRepo = new ModerationActionRepository($this->db);

        // --- Services ---
        $this->groupService      = new GroupService($groupRepo, $channelRepo, $membershipRepo);
        $this->membershipService = new MembershipService($groupRepo, $membershipRepo);
        $this->channelService    = new ChannelService($groupRepo, $channelRepo, $membershipRepo);
        $this->messageService    = new MessageService($groupRepo, $channelRepo, $membershipRepo, $messageRepo);
        $this->profileService    = new ProfileService($userRepo, $profileRepo);
        $this->dmService = new DmService($dmConvRepo, $dmMsgRepo, $userRepo);
        $this->moderationService = new ModerationService($userRepo, $channelRepo, $messageRepo, $moderationActionRepo);
        $this->notificationService = new NotificationService($this->db);
        $this->channelRepository = $channelRepo;
    }
}

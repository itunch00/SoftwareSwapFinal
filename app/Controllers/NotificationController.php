<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthGuard;
use App\Services\NotificationService;

final class NotificationController
{
    public function __construct(
        private AuthGuard $auth,
        private NotificationService $notifications
    ) {}

    /**
     * POST /notifications/clear
     */
    public function clearAll(): void
    {
        $user = $this->auth->mustBeLoggedIn();
        $this->notifications->clearAll((int)$user['id']); // ✅ correct property
        $redirect = $_POST['redirect_to'] ?? '/home';
        header('Location: ' . $redirect);
        exit;
    }
}
?>
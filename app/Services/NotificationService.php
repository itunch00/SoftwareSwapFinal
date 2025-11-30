<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class NotificationService
{
    public function __construct(private PDO $db) {}

    public function getForUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY time_sent DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clearAll(int $userId): void
{
        $stmt = $this->db->prepare("
            DELETE FROM notification_assignments
            WHERE user_id = :uid
        ");
        $stmt->execute([':uid' => $userId]);
    }
}
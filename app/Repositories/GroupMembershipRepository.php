<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GroupMembershipRepository
{
    public function __construct(private PDO $db) {}

    public function upsertActive(int $userId, int $groupId): void
    {
        $sql = "INSERT INTO `group_memberships` (`user_id`,`group_id`,`status`)
                VALUES (:uid, :gid, 'active')
                ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `joined_at` = CURRENT_TIMESTAMP, `left_at` = NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $userId, ':gid' => $groupId]);
    }

    public function markLeft(int $userId, int $groupId): void
    {
        $sql = "UPDATE `group_memberships`
                SET `status` = 'left', `left_at` = CURRENT_TIMESTAMP
                WHERE `user_id` = :uid AND `group_id` = :gid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $userId, ':gid' => $groupId]);
    }

    public function isMemberActive(int $userId, int $groupId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM `group_memberships`
             WHERE `user_id` = :uid AND `group_id` = :gid AND `status` = 'active' LIMIT 1"
        );
        $stmt->execute([':uid' => $userId, ':gid' => $groupId]);
        return (bool)$stmt->fetchColumn();
    }
}

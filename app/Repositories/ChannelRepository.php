<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ChannelRepository
{
    public function __construct(private PDO $db) {}

    public function listByGroupId(int $groupId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `channels` WHERE `group_id` = :gid ORDER BY `id` ASC");
        $stmt->execute([':gid' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

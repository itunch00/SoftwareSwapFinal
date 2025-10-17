<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ChannelRepository
{
    public function __construct(private PDO $db) {}

    public function create(array $data): int
    {
        $sql = "INSERT INTO `channels`
                (`group_id`,`name`,`slug`,`kind`,`is_readonly`,`created_by`)
                VALUES (:group_id,:name,:slug,:kind,:is_readonly,:created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':group_id'    => $data['group_id'],
            ':name'        => $data['name'],
            ':slug'        => $data['slug'],
            ':kind'        => $data['kind'] ?? 'general',
            ':is_readonly' => (int)($data['is_readonly'] ?? 0),
            ':created_by'  => $data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function listByGroupId(int $groupId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `channels` WHERE `group_id` = :gid ORDER BY `id` ASC"
        );
        $stmt->execute([':gid' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByGroupAndSlug(int $groupId, string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `channels` WHERE `group_id` = :gid AND `slug` = :slug LIMIT 1"
        );
        $stmt->execute([':gid' => $groupId, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function isSlugTaken(int $groupId, string $slug): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM `channels` WHERE `group_id` = :gid AND `slug` = :slug LIMIT 1"
        );
        $stmt->execute([':gid' => $groupId, ':slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }
}

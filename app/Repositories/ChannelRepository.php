<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ChannelRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Insert a channel row.
     *
     * @param array $data {group_id,name,slug,kind,is_readonly,created_by}
     * @return int        New channel id.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `channels`
                (`group_id`,`name`,`slug`,`kind`,`is_readonly`,`created_by`)
                VALUES (:group_id,:name,:slug,:kind,:is_readonly,:created_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':group_id'    => $data['group_id'],
            ':name'        => $data['name'],
            ':slug'        => $data['slug'],
            ':kind'        => $data['kind'] ?? 'general',
            ':is_readonly' => (int)($data['is_readonly'] ?? 0),
            ':created_by'  => $data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * List channels for a group.
     *
     * @param int $groupId Group id.
     * @return array       Channel rows.
     */
    public function listByGroupId(int $groupId): array
    {
        $st = $this->db->prepare("SELECT * FROM `channels` WHERE `group_id` = :gid ORDER BY `id` ASC");
        $st->execute([':gid' => $groupId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a channel by (group_id, slug).
     *
     * @param int    $groupId Group id.
     * @param string $slug    Channel slug.
     * @return array|null     Row or null.
     */
    public function findByGroupAndSlug(int $groupId, string $slug): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM `channels` WHERE `group_id` = :gid AND `slug` = :slug LIMIT 1"
        );
        $st->execute([':gid' => $groupId, ':slug' => $slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a slug exists for a given group.
     *
     * @param int    $groupId Group id.
     * @param string $slug    Candidate channel slug.
     * @return bool           True when taken.
     */
    public function isSlugTaken(int $groupId, string $slug): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM `channels` WHERE `group_id` = :gid AND `slug` = :slug LIMIT 1"
        );
        $st->execute([':gid' => $groupId, ':slug' => $slug]);
        return (bool)$st->fetchColumn();
    }
}

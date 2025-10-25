<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GroupRepository
{
    public function __construct(private PDO $db) {}

    public function create(array $data): int
    {
        $sql = "INSERT INTO `groups` (`name`,`slug`,`description`,`visibility`,`created_by`)
                VALUES (:name, :slug, :description, :visibility, :created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => $data['description'] ?? null,
            ':visibility' => $data['visibility'] ?? 'public',
            ':created_by' => $data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `groups` WHERE `slug` = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function isSlugTaken(string $slug): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM `groups` WHERE `slug` = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }

    /** Return groups where the user has ACTIVE membership */
    public function listByUserId(int $userId): array
    {
        $sql = "
            SELECT g.*
            FROM `groups` g
            JOIN `group_memberships` gm
              ON gm.group_id = g.id
             AND gm.user_id  = :uid
             AND gm.status   = 'active'
            ORDER BY g.created_at DESC, g.id DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** quick member counts per group id list */
    public function memberCounts(array $groupIds): array
    {
        if (empty($groupIds)) return [];
        $in = implode(',', array_fill(0, count($groupIds), '?'));
        $sql = "
            SELECT group_id, COUNT(*) AS cnt
            FROM group_memberships
            WHERE status = 'active' AND group_id IN ($in)
            GROUP BY group_id
        ";
        $st = $this->db->prepare($sql);
        $st->execute(array_values($groupIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['group_id']] = (int)$r['cnt'];
        }
        return $out;
    }

    /**
     * List public groups the user is NOT an active member of.
     *
     * @param int $userId
     * @return array
     */
    public function listPublicNotJoined(int $userId): array
    {
        $sql = "
            SELECT g.*
            FROM `groups` g
            LEFT JOIN `group_memberships` gm
            ON gm.group_id = g.id
            AND gm.user_id  = :uid
            AND gm.status   = 'active'
            WHERE g.visibility = 'public'
            AND gm.user_id IS NULL
            ORDER BY g.created_at DESC, g.id DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listAll(): array
    {
        $st = $this->db->query("SELECT * FROM `groups` ORDER BY created_at DESC, id DESC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteById(int $id): void
    {
        $st = $this->db->prepare("DELETE FROM `groups` WHERE id = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
    }
}

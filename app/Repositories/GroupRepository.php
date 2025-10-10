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
}

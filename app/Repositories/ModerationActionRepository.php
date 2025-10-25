<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ModerationActionRepository
{
    public function __construct(private PDO $db) {}

    public function log(int $adminId, string $action, string $targetType, int $targetId, ?string $reason, array $meta = []): void
    {
        $st = $this->db->prepare("
            INSERT INTO moderation_actions (admin_id, action, target_type, target_id, reason, meta_json)
            VALUES (:a, :ac, :tt, :tid, :r, :mj)
        ");
        $st->bindValue(':a',  $adminId, PDO::PARAM_INT);
        $st->bindValue(':ac', $action,   PDO::PARAM_STR);
        $st->bindValue(':tt', $targetType, PDO::PARAM_STR);
        $st->bindValue(':tid', $targetId, PDO::PARAM_INT);
        $st->bindValue(':r',  $reason, $reason !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':mj', $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, $meta ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
    }
}

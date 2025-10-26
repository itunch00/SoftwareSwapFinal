<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use DateTimeInterface;

class UserRepository
{
    public function __construct(private PDO $db) {}

    // public function findByEmail(string $email): ?array
    // {
    //     $st = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    //     $st->bindValue(':email', $email);
    //     $st->execute();
    //     $row = $st->fetch();
    //     return $row ?: null;
    // }

    public function create(string $email, string $hash, string $displayName, string $role = 'student'): int
    {
        $st = $this->db->prepare(
            'INSERT INTO users (email, password_hash, display_name, role)
             VALUES (:email, :hash, :name, :role)'
        );
        $st->execute([
            ':email' => $email,
            ':hash'  => $hash,
            ':name'  => $displayName,
            ':role'  => $role
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** @return void */
    public function updateDisplayName(int $id, string $name): void {
        $st = $this->db->prepare("UPDATE users SET display_name = :n WHERE id = :id");
        $st->execute([':n' => $name, ':id' => $id]);
    }

    // public function setBan(int $userId, ?DateTimeInterface $until, ?string $reason): void
    // {
    //     $sql = "UPDATE users SET banned_until = :bu, ban_reason = :br WHERE id = :id";
    //     $st  = $this->db->prepare($sql);
    //     $st->bindValue(':id', $userId, PDO::PARAM_INT);
    //     $st->bindValue(':bu', $until ? $until->format('Y-m-d H:i:s') : null, $until ? PDO::PARAM_STR : PDO::PARAM_NULL);
    //     $st->bindValue(':br', $reason !== null ? $reason : null, $reason !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    //     $st->execute();
    // }

    // public function isBanned(int $userId): bool
    // {
    //     $st = $this->db->prepare("SELECT banned_until FROM users WHERE id = :id");
    //     $st->bindValue(':id', $userId, PDO::PARAM_INT);
    //     $st->execute();
    //     $when = $st->fetchColumn();
    //     if (!$when) return false;
    //     return (new \DateTimeImmutable($when)) > new \DateTimeImmutable('now');
    // }

    public function findById(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $st = $this->db->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
        $st->bindValue(':e', $email, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function setBanById(int $userId, ?DateTimeInterface $until, ?string $reason): void
    {
        $st = $this->db->prepare("UPDATE users SET banned_until = :u, ban_reason = :r WHERE id = :id");
        $st->bindValue(':id', $userId, PDO::PARAM_INT);
        $st->bindValue(':u', $until ? $until->format('Y-m-d H:i:s') : null, $until ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':r', ($reason !== null && $reason !== '') ? $reason : null, ($reason !== null && $reason !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
    }

    public function clearBanById(int $userId): void
    {
        $st = $this->db->prepare("UPDATE users SET banned_until = NULL, ban_reason = NULL WHERE id = :id");
        $st->bindValue(':id', $userId, PDO::PARAM_INT);
        $st->execute();
    }

    public function listCurrentlyBanned(): array
    {
        $st = $this->db->query("
            SELECT id, email, display_name, banned_until, ban_reason
            FROM users
            WHERE banned_until IS NOT NULL AND banned_until > NOW()
            ORDER BY banned_until DESC
        ");
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}

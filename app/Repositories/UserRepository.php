<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findByEmail(string $email): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $st->bindValue(':email', $email);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

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
}

<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(private UserRepository $users) {}

    public function signup(string $email, string $password, string $displayName, string $role = 'student'): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email.');
        }
        if (strlen($password) < 6) {
            throw new \InvalidArgumentException('Password too short.');
        }
        if ($displayName === '') {
            throw new \InvalidArgumentException('Display name required.');
        }
        if ($this->users->findByEmail($email)) {
            throw new \RuntimeException('Email already registered.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->users->create($email, $hash, $displayName, $role);
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $user = $this->users->findByEmail($email);
        if (!$user) throw new \RuntimeException('Invalid credentials.');
        if (!password_verify($password, (string)$user['password_hash'])) {
            throw new \RuntimeException('Invalid credentials.');
        }
        return $user;
    }
}

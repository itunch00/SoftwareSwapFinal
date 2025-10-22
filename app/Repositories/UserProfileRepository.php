<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserProfileRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Fetch profile row for a user.
     *
     * @param int $userId User id.
     * @return array|null Profile row or null if none exists yet.
     */
    public function getByUserId(int $userId): ?array
    {
        $st = $this->db->prepare("SELECT * FROM user_profiles WHERE user_id = :uid LIMIT 1");
        $st->execute([':uid' => $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create or update a user's profile atomically.
     *
     * @param int   $userId User id.
     * @param array $data   Allowed keys: bio, website, github, linkedin, pronouns, timezone.
     * @return void
     */
    public function upsert(int $userId, array $data): void
    {
        $sql = "
            INSERT INTO user_profiles (user_id, bio, website, github, linkedin, pronouns, timezone, updated_at)
            VALUES (:uid, :bio, :website, :github, :linkedin, :pronouns, :tz, NOW())
            ON DUPLICATE KEY UPDATE
                bio = VALUES(bio),
                website = VALUES(website),
                github = VALUES(github),
                linkedin = VALUES(linkedin),
                pronouns = VALUES(pronouns),
                timezone = VALUES(timezone),
                updated_at = NOW()
        ";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':uid'      => $userId,
            ':bio'      => $data['bio'] ?? null,
            ':website'  => $data['website'] ?? null,
            ':github'   => $data['github'] ?? null,
            ':linkedin' => $data['linkedin'] ?? null,
            ':pronouns' => $data['pronouns'] ?? null,
            ':tz'       => $data['timezone'] ?? null,
        ]);
    }
}

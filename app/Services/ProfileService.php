<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\UserProfileRepository;
use Twig\Environment;

final class ProfileService
{
    public function __construct(
        private UserRepository $users,
        private UserProfileRepository $profiles
    ) {}

    /**
     * Return data needed to render /me (user & profile).
     *
     * @param int $userId Current user id.
     * @return array{user: array, profile: array|null}
     */
    public function getMeView(int $userId): array
    {
        $user = $this->users->findById($userId); // ensure you have this; add if missing.
        $profile = $this->profiles->getByUserId($userId);
        return ['user' => $user, 'profile' => $profile];
    }

    /**
     * Update the user's profile (and optionally their display_name).
     *
     * @param int   $userId  Current user id.
     * @param array $payload Form fields.
     * @return void
     */
    public function updateMe(int $userId, array $payload): void
    {
        // update display_name in users table (simple validation)
        $displayName = trim((string)($payload['display_name'] ?? ''));
        if ($displayName !== '') {
            $this->users->updateDisplayName($userId, $displayName);
        }

        // Normalize/validate profile fields
        $data = [
            'bio'      => $this->sanitizeText($payload['bio'] ?? null, 2000),
            'website'  => $this->sanitizeUrl($payload['website'] ?? null),
            'github'   => $this->sanitizeUrl($payload['github'] ?? null),
            'linkedin' => $this->sanitizeUrl($payload['linkedin'] ?? null),
            'pronouns' => $this->sanitizeText($payload['pronouns'] ?? null, 60),
            'timezone' => $this->sanitizeText($payload['timezone'] ?? null, 64),
        ];

        $this->profiles->upsert($userId, $data);
    }

    /** Trim + clamp length; null if empty after trim. */
    private function sanitizeText(?string $s, int $max): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        if ($s === '') return null;
        if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
        return $s;
    }

    /** Basic absolute URL sanity; null if empty/invalid. */
    private function sanitizeUrl(?string $u): ?string
    {
        if ($u === null) return null;
        $u = trim($u);
        if ($u === '') return null;
        //If user enters handle like "github.com/name", add scheme
        if (!preg_match('#^https?://#i', $u)) {
            $u = 'https://' . $u;
        }
        return filter_var($u, FILTER_VALIDATE_URL) ? $u : null;
    }

}
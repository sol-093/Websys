<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class AuthRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findSessionUserById(int $userId): array|false
    {
        $stmt = $this->db->prepare('SELECT id, name, email, role, onboarding_done, institute, program, year_level, section, profile_picture_path, profile_picture_crop_x, profile_picture_crop_y, profile_picture_zoom, email_verified, account_status, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        return $stmt->fetch();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findUserByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        return $stmt->fetch();
    }

    public function countOwnedOrganizations(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM organizations WHERE owner_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function downgradeUserToStudent(int $userId): void
    {
        $stmt = $this->db->prepare("UPDATE users SET role = 'student' WHERE id = ?");
        $stmt->execute([$userId]);
    }
}

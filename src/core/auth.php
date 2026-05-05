<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - AUTHENTICATION GUARDS
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. currentUser()
 * 2. requireLogin()
 * 3. requireRole()
 *
 * EDIT GUIDE:
 * - Edit this file for login/session lookup and role guard behavior.
 * ================================================
 */

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role, onboarding_done, institute, program, year_level, section, profile_picture_path, profile_picture_crop_x, profile_picture_crop_y, profile_picture_zoom, email_verified, account_status, created_at FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    if (($user['role'] ?? '') === 'owner') {
        $ownerCheck = db()->prepare('SELECT COUNT(*) FROM organizations WHERE owner_id = ?');
        $ownerCheck->execute([(int) $user['id']]);
        $ownedCount = (int) $ownerCheck->fetchColumn();

        if ($ownedCount === 0) {
            $downgrade = db()->prepare("UPDATE users SET role = 'student' WHERE id = ?");
            $downgrade->execute([(int) $user['id']]);
            $user['role'] = 'student';
        }
    }

    return $user;
}

function requireLogin(): void
{
    if (!currentUser()) {
        setFlash('error', 'Please login first.');
        redirect('?page=login');
    }
}

function requireRole(array $roles): void
{
    $user = currentUser();
    if (!$user || !in_array($user['role'], $roles, true)) {
        setFlash('error', 'You are not authorized to access that page.');
        redirect('?page=dashboard');
    }
}

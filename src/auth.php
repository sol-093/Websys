<?php

declare(strict_types=1);

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role, institute, program FROM users WHERE id = ?');
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

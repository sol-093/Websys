<?php

declare(strict_types=1);

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
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

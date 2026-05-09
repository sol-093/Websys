<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - AUTHENTICATION GUARDS
 * ================================================
 *
 * SECTION MAP:
 * 1. currentUser()
 * 2. requireLogin()
 * 3. requireRole()
 *
 * WORK GUIDE:
 * - Edit this file for login/session lookup and role guard behavior.
 * ================================================
 */

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $users = new Involve\Repositories\AuthRepository(db());
    $user = $users->findSessionUserById((int) $_SESSION['user_id']);
    if (!$user) {
        return null;
    }

    if (($user['role'] ?? '') === 'owner') {
        $ownedCount = $users->countOwnedOrganizations((int) $user['id']);

        if ($ownedCount === 0) {
            $users->downgradeUserToStudent((int) $user['id']);
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

function permissionGate(): Involve\Auth\PermissionGate
{
    static $gate = null;

    if (!$gate instanceof Involve\Auth\PermissionGate) {
        $gate = Involve\Auth\PermissionGate::fromConfigPath(dirname(__DIR__, 2) . '/config/permissions.php');
    }

    return $gate;
}

function can(string $permission, ?array $user = null): bool
{
    return permissionGate()->allows($permission, $user ?? currentUser());
}

function requirePermission(string $permission): void
{
    if (!can($permission)) {
        setFlash('error', 'You are not authorized to access that page.');
        redirect('?page=dashboard');
    }
}

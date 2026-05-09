<?php

declare(strict_types=1);

namespace Involve\Auth;

final class PermissionGate
{
    /** @var array<string, list<string>> */
    private array $rolePermissions;

    /**
     * @param array<string, list<string>> $rolePermissions
     */
    public function __construct(array $rolePermissions)
    {
        $this->rolePermissions = $rolePermissions;
    }

    public static function fromConfigPath(string $path): self
    {
        $config = is_file($path) ? require $path : [];
        $roles = is_array($config) && isset($config['roles']) && is_array($config['roles'])
            ? $config['roles']
            : [];

        return new self($roles);
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function allows(string $permission, ?array $user): bool
    {
        $permission = trim($permission);
        if ($permission === '' || $user === null) {
            return false;
        }

        $role = (string) ($user['role'] ?? '');
        if ($role === '') {
            return false;
        }

        return in_array($permission, $this->rolePermissions[$role] ?? [], true);
    }
}

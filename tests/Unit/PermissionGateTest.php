<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Auth\PermissionGate;
use PHPUnit\Framework\TestCase;

final class PermissionGateTest extends TestCase
{
    public function testAdminOwnerAndStudentPermissions(): void
    {
        $gate = PermissionGate::fromConfigPath(dirname(__DIR__, 2) . '/config/permissions.php');

        self::assertTrue($gate->allows('view_admin', ['role' => 'admin']));
        self::assertTrue($gate->allows('manage_own_organization', ['role' => 'owner']));
        self::assertTrue($gate->allows('join_organizations', ['role' => 'student']));
        self::assertFalse($gate->allows('approve_transactions', ['role' => 'student']));
    }
}

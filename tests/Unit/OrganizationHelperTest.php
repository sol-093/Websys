<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/lib/organization.php';

final class OrganizationHelperTest extends TestCase
{
    public function testVisibilityAndJoinEligibilityHelpers(): void
    {
        $student = ['role' => 'student', 'institute' => 'Institute of Computing and Digital Innovations', 'program' => 'BS Information Systems'];
        $collegewide = ['org_category' => 'collegewide'];
        $institute = ['org_category' => 'institutewide', 'target_institute' => 'Institute of Computing and Digital Innovations'];
        $program = ['org_category' => 'program_based', 'target_program' => 'BS Nursing'];

        self::assertSame('Collegewide', getOrganizationVisibilityLabel($collegewide));
        self::assertTrue(canUserJoinOrganization($collegewide, $student));
        self::assertTrue(canUserJoinOrganization($institute, $student));
        self::assertFalse(canUserJoinOrganization($program, $student));
        self::assertSame('Restricted (Program mismatch)', getJoinRestrictionLabel($program));
    }
}

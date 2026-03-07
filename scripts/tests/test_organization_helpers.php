<?php

declare(strict_types=1);

require __DIR__ . '/../../src/lib/organization.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            'Assertion failed: ' . $message . ' | expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true)
        );
    }
}

$orgs = [
    ['id' => 1, 'name' => 'Zeta Club', 'org_category' => 'collegewide', 'target_institute' => null, 'target_program' => null],
    ['id' => 2, 'name' => 'CDI Society', 'org_category' => 'institutewide', 'target_institute' => 'Institute of Computing and Digital Innovations', 'target_program' => null],
    ['id' => 3, 'name' => 'DS Circle', 'org_category' => 'program_based', 'target_institute' => 'Institute of Computing and Digital Innovations', 'target_program' => 'BS Data Science'],
];

$studentDS = [
    'role' => 'student',
    'institute' => 'Institute of Computing and Digital Innovations',
    'program' => 'BS Data Science',
];

$studentNursing = [
    'role' => 'student',
    'institute' => 'Institute of Nursing',
    'program' => 'BS Nursing',
];

$admin = [
    'role' => 'admin',
    'institute' => '',
    'program' => '',
];

$visibleToDS = applyOrganizationVisibilityForUser($orgs, $studentDS);
assertSameValue(3, count($visibleToDS), 'DS student should see all matching categories including restricted matches');

$visibleToNursing = applyOrganizationVisibilityForUser($orgs, $studentNursing);
assertSameValue(1, count($visibleToNursing), 'Nursing student should only see collegewide org in this dataset');
assertSameValue('collegewide', (string) $visibleToNursing[0]['org_category'], 'Visible org should be collegewide');

$visibleToAdmin = applyOrganizationVisibilityForUser($orgs, $admin);
assertSameValue(3, count($visibleToAdmin), 'Admin should see all organizations');

assertTrue(canUserJoinOrganization($orgs[0], $studentNursing), 'Any student should join collegewide org');
assertTrue(!canUserJoinOrganization($orgs[1], $studentNursing), 'Nursing student should not join CDI institutewide org');
assertTrue(canUserJoinOrganization($orgs[1], $studentDS), 'CDI student should join matching institutewide org');
assertTrue(canUserJoinOrganization($orgs[2], $studentDS), 'DS student should join matching program-based org');
assertTrue(!canUserJoinOrganization($orgs[2], $studentNursing), 'Nursing student should not join DS program-based org');

assertSameValue('Collegewide', getOrganizationVisibilityLabel($orgs[0]), 'Collegewide label should be correct');
assertSameValue('Institutewide • Institute of Computing and Digital Innovations', getOrganizationVisibilityLabel($orgs[1]), 'Institutewide label should include institute');
assertSameValue('Program-based • BS Data Science', getOrganizationVisibilityLabel($orgs[2]), 'Program label should include program');

echo "All organization helper tests passed.\n";

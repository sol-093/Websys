<?php

declare(strict_types=1);

require __DIR__ . '/../../src/core/db.php';
require __DIR__ . '/../../src/lib/organization.php';

$pdo = db();
$password = 'ExtraSeed123!';

function extraSeedUserId(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function extraSeedUpsertUser(PDO $pdo, array $user, string $password): int
{
    $existingId = extraSeedUserId($pdo, (string) $user['email']);
    $program = $user['program'] ?? null;
    $institute = getInstituteForProgram($program);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($existingId !== null) {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET name = ?, role = ?, password_hash = ?, onboarding_done = 1, institute = ?, program = ?, year_level = ?, section = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $user['name'],
            $user['role'],
            $passwordHash,
            $institute,
            $program,
            $user['year_level'] ?? null,
            $user['section'] ?? null,
            $existingId,
        ]);

        return $existingId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, onboarding_done, institute, program, year_level, section)
         VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['name'],
        $user['email'],
        $passwordHash,
        $user['role'],
        $institute,
        $program,
        $user['year_level'] ?? null,
        $user['section'] ?? null,
    ]);

    return (int) $pdo->lastInsertId();
}

function extraSeedOrganizationId(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM organizations WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function extraSeedUpsertOrganization(PDO $pdo, array $org, ?int $ownerId): int
{
    $existingId = extraSeedOrganizationId($pdo, (string) $org['name']);

    if ($existingId !== null) {
        $stmt = $pdo->prepare(
            'UPDATE organizations
             SET description = ?, org_category = ?, target_institute = ?, target_program = ?, owner_id = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $org['description'],
            $org['org_category'],
            $org['target_institute'] ?? null,
            $org['target_program'] ?? null,
            $ownerId,
            $existingId,
        ]);

        return $existingId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO organizations (name, description, org_category, target_institute, target_program, owner_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $org['name'],
        $org['description'],
        $org['org_category'],
        $org['target_institute'] ?? null,
        $org['target_program'] ?? null,
        $ownerId,
    ]);

    return (int) $pdo->lastInsertId();
}

function extraSeedEnsureMembership(PDO $pdo, int $organizationId, int $userId): void
{
    $stmt = $pdo->prepare('SELECT id FROM organization_members WHERE organization_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$organizationId, $userId]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO organization_members (organization_id, user_id) VALUES (?, ?)');
    $stmt->execute([$organizationId, $userId]);
}

function extraSeedEnsureAnnouncement(PDO $pdo, int $organizationId, string $title, string $content): void
{
    $stmt = $pdo->prepare('SELECT id FROM announcements WHERE organization_id = ? AND title = ? LIMIT 1');
    $stmt->execute([$organizationId, $title]);

    if ($stmt->fetchColumn() !== false) {
        $update = $pdo->prepare('UPDATE announcements SET content = ?, duration_days = 45, expires_at = ? WHERE organization_id = ? AND title = ?');
        $update->execute([$content, (new DateTimeImmutable('+45 days'))->format('Y-m-d H:i:s'), $organizationId, $title]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO announcements (organization_id, title, content, duration_days, expires_at) VALUES (?, ?, ?, 45, ?)');
    $stmt->execute([$organizationId, $title, $content, (new DateTimeImmutable('+45 days'))->format('Y-m-d H:i:s')]);
}

$users = [
    ['name' => 'Sophia Tan', 'email' => 'extra.sophia@campus.local', 'role' => 'owner', 'program' => 'BS Information Systems', 'year_level' => 4, 'section' => 'B'],
    ['name' => 'Nico Valdez', 'email' => 'extra.nico@campus.local', 'role' => 'owner', 'program' => 'BS Computer Science', 'year_level' => 3, 'section' => 'A'],
    ['name' => 'Bianca Cruz', 'email' => 'extra.bianca@campus.local', 'role' => 'owner', 'program' => 'BS Psychology', 'year_level' => 4, 'section' => 'C'],
    ['name' => 'Miguel Torres', 'email' => 'extra.miguel@campus.local', 'role' => 'student', 'program' => 'BS Information Systems', 'year_level' => 2, 'section' => 'A'],
    ['name' => 'Denise Ramos', 'email' => 'extra.denise@campus.local', 'role' => 'student', 'program' => 'BS Computer Science', 'year_level' => 1, 'section' => 'B'],
    ['name' => 'Carlo Mendoza', 'email' => 'extra.carlo@campus.local', 'role' => 'student', 'program' => 'BS Civil Engineering', 'year_level' => 3, 'section' => 'A'],
    ['name' => 'Yna Castillo', 'email' => 'extra.yna@campus.local', 'role' => 'student', 'program' => 'BS Nursing', 'year_level' => 2, 'section' => 'C'],
    ['name' => 'Theo Garcia', 'email' => 'extra.theo@campus.local', 'role' => 'student', 'program' => 'BS Data Science', 'year_level' => 2, 'section' => 'B'],
];

$orgs = [
    [
        'name' => 'Innovation Lab Council',
        'description' => 'A collegewide student group for product demos, prototype showcases, and innovation clinics.',
        'org_category' => 'collegewide',
        'owner_email' => 'extra.sophia@campus.local',
    ],
    [
        'name' => 'Cybersecurity Student Network',
        'description' => 'Workshops and capture-the-flag practice sessions for computing students.',
        'org_category' => 'institutewide',
        'target_institute' => 'Institute of Computing and Digital Innovations',
        'owner_email' => 'extra.nico@campus.local',
    ],
    [
        'name' => 'Human Behavior Research Circle',
        'description' => 'Peer-led research discussions for psychology and behavioral science students.',
        'org_category' => 'program_based',
        'target_institute' => 'Institute of Arts and Sciences',
        'target_program' => 'BS Psychology',
        'owner_email' => 'extra.bianca@campus.local',
    ],
    [
        'name' => 'Campus Events Production Team',
        'description' => 'A cross-program group that supports stage management, registration, and event logistics.',
        'org_category' => 'collegewide',
        'owner_email' => 'extra.sophia@campus.local',
    ],
];

$pdo->beginTransaction();

try {
    $userIds = [];
    foreach ($users as $user) {
        $userIds[$user['email']] = extraSeedUpsertUser($pdo, $user, $password);
    }

    $orgIds = [];
    foreach ($orgs as $org) {
        $ownerId = $userIds[$org['owner_email']] ?? null;
        $orgIds[$org['name']] = extraSeedUpsertOrganization($pdo, $org, $ownerId);
    }

    $memberships = [
        'Innovation Lab Council' => ['extra.sophia@campus.local', 'extra.miguel@campus.local', 'extra.denise@campus.local', 'extra.theo@campus.local'],
        'Cybersecurity Student Network' => ['extra.nico@campus.local', 'extra.denise@campus.local', 'extra.theo@campus.local'],
        'Human Behavior Research Circle' => ['extra.bianca@campus.local'],
        'Campus Events Production Team' => ['extra.sophia@campus.local', 'extra.carlo@campus.local', 'extra.yna@campus.local', 'extra.miguel@campus.local'],
    ];

    foreach ($memberships as $orgName => $emails) {
        foreach ($emails as $email) {
            extraSeedEnsureMembership($pdo, $orgIds[$orgName], $userIds[$email]);
        }
    }

    foreach ($orgs as $org) {
        extraSeedEnsureAnnouncement(
            $pdo,
            $orgIds[$org['name']],
            $org['name'] . ' Planning Notice',
            'This extra seeded announcement gives the dashboard and community pages more realistic activity to display.'
        );
    }

    $pdo->commit();

    fwrite(STDOUT, "Extra dummy data seeded successfully.\n");
    fwrite(STDOUT, "Extra seed login password: {$password}\n");
    fwrite(STDOUT, 'Extra users: ' . count($users) . PHP_EOL);
    fwrite(STDOUT, 'Extra organizations: ' . count($orgs) . PHP_EOL);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Extra dummy data seeding failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

require __DIR__ . '/../src/db.php';

$pdo = db();
$now = new DateTimeImmutable('now');

function findUserIdByEmail(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function upsertUser(PDO $pdo, string $name, string $email, string $role, string $password): int
{
    $existingId = findUserIdByEmail($pdo, $email);

    if ($existingId !== null) {
        $update = $pdo->prepare('UPDATE users SET name = ?, role = ?, password_hash = ? WHERE id = ?');
        $update->execute([$name, $role, password_hash($password, PASSWORD_DEFAULT), $existingId]);

        return $existingId;
    }

    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);

    return (int) $pdo->lastInsertId();
}

function findOrganizationIdByName(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM organizations WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function upsertOrganization(PDO $pdo, string $name, string $description, ?int $ownerId): int
{
    $existingId = findOrganizationIdByName($pdo, $name);

    if ($existingId !== null) {
        $update = $pdo->prepare('UPDATE organizations SET description = ?, owner_id = ? WHERE id = ?');
        $update->execute([$description, $ownerId, $existingId]);

        return $existingId;
    }

    $insert = $pdo->prepare('INSERT INTO organizations (name, description, owner_id) VALUES (?, ?, ?)');
    $insert->execute([$name, $description, $ownerId]);

    return (int) $pdo->lastInsertId();
}

function ensureMembership(PDO $pdo, int $organizationId, int $userId): void
{
    $stmt = $pdo->prepare('SELECT id FROM organization_members WHERE organization_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$organizationId, $userId]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO organization_members (organization_id, user_id) VALUES (?, ?)');
    $insert->execute([$organizationId, $userId]);
}

function ensureAnnouncement(PDO $pdo, int $organizationId, string $title, string $content): void
{
    $stmt = $pdo->prepare('SELECT id FROM announcements WHERE organization_id = ? AND title = ? LIMIT 1');
    $stmt->execute([$organizationId, $title]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO announcements (organization_id, title, content) VALUES (?, ?, ?)');
    $insert->execute([$organizationId, $title, $content]);
}

function ensureTransaction(
    PDO $pdo,
    int $organizationId,
    string $type,
    float $amount,
    string $description,
    string $transactionDate
): int {
    $stmt = $pdo->prepare('SELECT id FROM financial_transactions WHERE organization_id = ? AND description = ? AND transaction_date = ? LIMIT 1');
    $stmt->execute([$organizationId, $description, $transactionDate]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        $update = $pdo->prepare('UPDATE financial_transactions SET type = ?, amount = ? WHERE id = ?');
        $update->execute([$type, $amount, (int) $existingId]);

        return (int) $existingId;
    }

    $insert = $pdo->prepare('INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path) VALUES (?, ?, ?, ?, ?, NULL)');
    $insert->execute([$organizationId, $type, $amount, $description, $transactionDate]);

    return (int) $pdo->lastInsertId();
}

function upsertOwnerAssignment(PDO $pdo, int $organizationId, int $studentId, string $status): void
{
    $stmt = $pdo->prepare('SELECT id FROM owner_assignments WHERE organization_id = ? LIMIT 1');
    $stmt->execute([$organizationId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        $update = $pdo->prepare('UPDATE owner_assignments SET student_id = ?, status = ? WHERE id = ?');
        $update->execute([$studentId, $status, (int) $existingId]);
        return;
    }

    $insert = $pdo->prepare('INSERT INTO owner_assignments (organization_id, student_id, status) VALUES (?, ?, ?)');
    $insert->execute([$organizationId, $studentId, $status]);
}

function upsertJoinRequest(PDO $pdo, int $organizationId, int $userId, string $status): void
{
    $stmt = $pdo->prepare('SELECT id FROM organization_join_requests WHERE organization_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$organizationId, $userId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        $update = $pdo->prepare('UPDATE organization_join_requests SET status = ? WHERE id = ?');
        $update->execute([$status, (int) $existingId]);
        return;
    }

    $insert = $pdo->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status) VALUES (?, ?, ?)');
    $insert->execute([$organizationId, $userId, $status]);
}

function upsertTransactionChangeRequest(
    PDO $pdo,
    int $transactionId,
    int $organizationId,
    int $requestedBy,
    string $actionType,
    string $status,
    ?string $proposedType,
    ?float $proposedAmount,
    ?string $proposedDescription,
    ?string $proposedTransactionDate,
    ?string $adminNote
): void {
    $stmt = $pdo->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND requested_by = ? AND action_type = ? LIMIT 1');
    $stmt->execute([$transactionId, $requestedBy, $actionType]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        $update = $pdo->prepare('UPDATE transaction_change_requests SET status = ?, proposed_type = ?, proposed_amount = ?, proposed_description = ?, proposed_transaction_date = ?, admin_note = ? WHERE id = ?');
        $update->execute([
            $status,
            $proposedType,
            $proposedAmount,
            $proposedDescription,
            $proposedTransactionDate,
            $adminNote,
            (int) $existingId,
        ]);
        return;
    }

    $insert = $pdo->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, proposed_type, proposed_amount, proposed_description, proposed_transaction_date, status, admin_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        $transactionId,
        $organizationId,
        $requestedBy,
        $actionType,
        $proposedType,
        $proposedAmount,
        $proposedDescription,
        $proposedTransactionDate,
        $status,
        $adminNote,
    ]);
}

function resetDemoTransactions(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id FROM financial_transactions WHERE description LIKE '[DEMO]%' OR description LIKE '% seed income' OR description LIKE '% seed expense' OR description LIKE 'Membership dues collection' OR description LIKE 'Hackathon materials' OR description LIKE 'Event sponsorship' OR description LIKE 'Stage and lighting rental' OR description LIKE 'Fundraising proceeds' OR description LIKE 'Sensor and controller kits' ");
    $ids = array_map(static fn($value): int => (int) $value, $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (count($ids) === 0) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $deleteRequests = $pdo->prepare("DELETE FROM transaction_change_requests WHERE transaction_id IN ($placeholders)");
    $deleteRequests->execute($ids);

    $deleteTransactions = $pdo->prepare("DELETE FROM financial_transactions WHERE id IN ($placeholders)");
    $deleteTransactions->execute($ids);
}

function normalizeSeedName(string $value): string
{
    return str_replace('Demo ', '', $value);
}

function normalizeSeedEmail(string $value): string
{
    return str_replace('demo.', 'seed.', $value);
}

function normalizeSeedLabel(string $value): string
{
    $value = str_replace('[DEMO] ', '', $value);
    $value = str_replace('Demo ', '', $value);

    return $value;
}

$pdo->beginTransaction();

try {
    $accounts = [
        ['name' => 'Demo Admin', 'email' => 'demo.admin@campus.local', 'role' => 'admin'],
        ['name' => 'Lia Santos', 'email' => 'demo.lia@campus.local', 'role' => 'owner'],
        ['name' => 'Noah Cruz', 'email' => 'demo.noah@campus.local', 'role' => 'owner'],
        ['name' => 'Zoe Tan', 'email' => 'demo.zoe@campus.local', 'role' => 'owner'],
        ['name' => 'Ian Velasco', 'email' => 'demo.ian@campus.local', 'role' => 'owner'],
        ['name' => 'Pat Lopez', 'email' => 'demo.pat@campus.local', 'role' => 'owner'],
        ['name' => 'Aira Gomez', 'email' => 'demo.aira@campus.local', 'role' => 'student'],
        ['name' => 'Evan Ramos', 'email' => 'demo.evan@campus.local', 'role' => 'student'],
        ['name' => 'Mia Flores', 'email' => 'demo.mia@campus.local', 'role' => 'student'],
        ['name' => 'Kai Dela Cruz', 'email' => 'demo.kai@campus.local', 'role' => 'student'],
        ['name' => 'Tess Lim', 'email' => 'demo.tess@campus.local', 'role' => 'student'],
        ['name' => 'Rin Navarro', 'email' => 'demo.rin@campus.local', 'role' => 'student'],
        ['name' => 'Jules Mercado', 'email' => 'demo.jules@campus.local', 'role' => 'student'],
        ['name' => 'Sara Ong', 'email' => 'demo.sara@campus.local', 'role' => 'student'],
        ['name' => 'Theo Bautista', 'email' => 'demo.theo@campus.local', 'role' => 'student'],
    ];

    $userIdsByEmail = [];
    foreach ($accounts as $account) {
        $normalizedName = normalizeSeedName((string) $account['name']);
        $normalizedEmail = normalizeSeedEmail((string) $account['email']);
        $userIdsByEmail[$account['email']] = upsertUser(
            $pdo,
            $normalizedName,
            $normalizedEmail,
            $account['role'],
            'SeedPass123!'
        );
    }

    $orgs = [
        [
            'name' => 'Demo Computing Society',
            'description' => 'Testing group for software and web activities.',
            'owner_email' => 'demo.lia@campus.local',
        ],
        [
            'name' => 'Demo Arts Collective',
            'description' => 'Testing group for visual and performing arts.',
            'owner_email' => 'demo.noah@campus.local',
        ],
        [
            'name' => 'Demo Robotics Club',
            'description' => 'Testing group for robotics workshops and demos.',
            'owner_email' => 'demo.noah@campus.local',
        ],
        [
            'name' => 'Demo Debate Union',
            'description' => 'Testing group for public speaking and debate events.',
            'owner_email' => 'demo.zoe@campus.local',
        ],
        [
            'name' => 'Demo Music Ensemble',
            'description' => 'Testing group for band rehearsals and recitals.',
            'owner_email' => 'demo.ian@campus.local',
        ],
        [
            'name' => 'Demo Science Guild',
            'description' => 'Testing group for science fair and research exhibits.',
            'owner_email' => 'demo.pat@campus.local',
        ],
        [
            'name' => 'Demo Entrepreneurship Circle',
            'description' => 'Testing group for startup pitch and business events.',
            'owner_email' => 'demo.lia@campus.local',
        ],
        [
            'name' => 'Demo Environmental Advocates',
            'description' => 'Testing group for sustainability and campus drives.',
            'owner_email' => 'demo.noah@campus.local',
        ],
        [
            'name' => 'Demo Photography Club',
            'description' => 'Testing group for media coverage and photo walks.',
            'owner_email' => 'demo.zoe@campus.local',
        ],
        [
            'name' => 'Demo Volunteer Network',
            'description' => 'Testing group for outreach and community service.',
            'owner_email' => 'demo.ian@campus.local',
        ],
        [
            'name' => 'Demo Language Society',
            'description' => 'Testing group for language exchange sessions.',
            'owner_email' => 'demo.pat@campus.local',
        ],
        [
            'name' => 'Demo Athletics Council',
            'description' => 'Testing group for sports tournaments and clinics.',
            'owner_email' => 'demo.lia@campus.local',
        ],
        [
            'name' => 'Demo Gaming & Esports',
            'description' => 'Testing group for esports leagues and game nights.',
            'owner_email' => 'demo.noah@campus.local',
        ],
    ];

    $orgIdsByName = [];
    foreach ($orgs as $organization) {
        $normalizedName = normalizeSeedName((string) $organization['name']);
        $normalizedDescription = str_replace('demos.', 'activities.', (string) $organization['description']);
        $normalizedOwnerEmail = normalizeSeedEmail((string) $organization['owner_email']);
        $ownerId = $userIdsByEmail[$organization['owner_email']] ?? null;
        $orgIdsByName[$organization['name']] = upsertOrganization(
            $pdo,
            $normalizedName,
            $normalizedDescription,
            $ownerId
        );
        $orgIdsByName[$normalizedName] = $orgIdsByName[$organization['name']];
    }

    $membershipMatrix = [
        'Demo Computing Society' => [
            'demo.lia@campus.local',
            'demo.aira@campus.local',
            'demo.evan@campus.local',
            'demo.tess@campus.local',
        ],
        'Demo Arts Collective' => [
            'demo.noah@campus.local',
            'demo.mia@campus.local',
            'demo.kai@campus.local',
        ],
        'Demo Robotics Club' => [
            'demo.noah@campus.local',
            'demo.aira@campus.local',
            'demo.kai@campus.local',
            'demo.tess@campus.local',
        ],
        'Demo Debate Union' => [
            'demo.zoe@campus.local',
            'demo.rin@campus.local',
            'demo.jules@campus.local',
        ],
        'Demo Music Ensemble' => [
            'demo.ian@campus.local',
            'demo.sara@campus.local',
            'demo.theo@campus.local',
        ],
        'Demo Science Guild' => [
            'demo.pat@campus.local',
            'demo.aira@campus.local',
            'demo.rin@campus.local',
        ],
        'Demo Entrepreneurship Circle' => [
            'demo.lia@campus.local',
            'demo.evan@campus.local',
            'demo.sara@campus.local',
        ],
        'Demo Environmental Advocates' => [
            'demo.noah@campus.local',
            'demo.mia@campus.local',
            'demo.theo@campus.local',
        ],
        'Demo Photography Club' => [
            'demo.zoe@campus.local',
            'demo.kai@campus.local',
            'demo.jules@campus.local',
        ],
        'Demo Volunteer Network' => [
            'demo.ian@campus.local',
            'demo.tess@campus.local',
            'demo.rin@campus.local',
        ],
        'Demo Language Society' => [
            'demo.pat@campus.local',
            'demo.sara@campus.local',
            'demo.jules@campus.local',
        ],
        'Demo Athletics Council' => [
            'demo.lia@campus.local',
            'demo.theo@campus.local',
            'demo.kai@campus.local',
        ],
        'Demo Gaming & Esports' => [
            'demo.noah@campus.local',
            'demo.evan@campus.local',
            'demo.tess@campus.local',
        ],
    ];

    foreach ($membershipMatrix as $orgName => $emails) {
        $organizationId = $orgIdsByName[$orgName];
        foreach ($emails as $email) {
            ensureMembership($pdo, $organizationId, $userIdsByEmail[$email]);
        }
    }

    ensureAnnouncement(
        $pdo,
        $orgIdsByName['Demo Computing Society'],
        'Welcome to Computing Society',
        'This is test content for announcement rendering and listing.'
    );
    ensureAnnouncement(
        $pdo,
        $orgIdsByName['Demo Arts Collective'],
        'Arts Week Schedule',
        'Testing schedule announcement for cards and detail views.'
    );
    ensureAnnouncement(
        $pdo,
        $orgIdsByName['Demo Robotics Club'],
        'Build Session Invite',
        'Testing robotics invite announcement and timeline order.'
    );

    foreach ($orgs as $organization) {
        $orgName = $organization['name'];
        if (in_array($orgName, ['Demo Computing Society', 'Demo Arts Collective', 'Demo Robotics Club'], true)) {
            continue;
        }

        ensureAnnouncement(
            $pdo,
            $orgIdsByName[$orgName],
            normalizeSeedLabel($orgName) . ' Weekly Update',
            'Automated weekly announcement content for pagination, carousel, and expiring announcement tests.'
        );
    }

    resetDemoTransactions($pdo);

    $tx1 = ensureTransaction(
        $pdo,
        $orgIdsByName['Demo Computing Society'],
        'income',
        12000.00,
        'Membership dues collection',
        '2025-12-12'
    );
    $tx2 = ensureTransaction(
        $pdo,
        $orgIdsByName['Demo Computing Society'],
        'expense',
        3200.50,
        'Hackathon materials',
        '2026-01-09'
    );
    $tx3 = ensureTransaction(
        $pdo,
        $orgIdsByName['Demo Arts Collective'],
        'income',
        8500.00,
        'Event sponsorship',
        '2026-01-18'
    );
    $tx4 = ensureTransaction(
        $pdo,
        $orgIdsByName['Demo Arts Collective'],
        'expense',
        4100.75,
        'Stage and lighting rental',
        '2026-02-01'
    );
    $tx5 = ensureTransaction(
        $pdo,
        $orgIdsByName['Demo Robotics Club'],
        'income',
        15000.00,
        'Fundraising proceeds',
        '2025-11-24'
    );
    $tx6 = ensureTransaction(
        $pdo,
        $orgIdsByName['Demo Robotics Club'],
        'expense',
        6800.00,
        'Sensor and controller kits',
        '2026-02-23'
    );

    $extraIncomeDates = ['2026-01-05', '2026-01-12', '2026-01-19', '2026-01-26', '2026-02-02', '2026-02-09', '2026-02-16', '2026-02-20', '2026-02-22', '2026-02-24'];
    $extraExpenseDates = ['2026-01-08', '2026-01-15', '2026-01-22', '2026-01-29', '2026-02-05', '2026-02-11', '2026-02-18', '2026-02-21', '2026-02-23', '2026-02-25'];
    $extraOrgNames = [
        'Demo Debate Union',
        'Demo Music Ensemble',
        'Demo Science Guild',
        'Demo Entrepreneurship Circle',
        'Demo Environmental Advocates',
        'Demo Photography Club',
        'Demo Volunteer Network',
        'Demo Language Society',
        'Demo Athletics Council',
        'Demo Gaming & Esports',
    ];

    foreach ($extraOrgNames as $index => $orgName) {
        ensureTransaction(
            $pdo,
            $orgIdsByName[$orgName],
            'income',
            5000.00 + ($index * 650),
            normalizeSeedLabel($orgName) . ' seed income',
            $extraIncomeDates[$index]
        );

        ensureTransaction(
            $pdo,
            $orgIdsByName[$orgName],
            'expense',
            2100.00 + ($index * 390),
            normalizeSeedLabel($orgName) . ' seed expense',
            $extraExpenseDates[$index]
        );
    }

    upsertOwnerAssignment(
        $pdo,
        $orgIdsByName['Demo Computing Society'],
        $userIdsByEmail['demo.aira@campus.local'],
        'accepted'
    );
    upsertOwnerAssignment(
        $pdo,
        $orgIdsByName['Demo Arts Collective'],
        $userIdsByEmail['demo.mia@campus.local'],
        'pending'
    );
    upsertOwnerAssignment(
        $pdo,
        $orgIdsByName['Demo Robotics Club'],
        $userIdsByEmail['demo.kai@campus.local'],
        'declined'
    );

    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Demo Computing Society'],
        $userIdsByEmail['demo.mia@campus.local'],
        'approved'
    );
    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Demo Arts Collective'],
        $userIdsByEmail['demo.tess@campus.local'],
        'pending'
    );
    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Demo Robotics Club'],
        $userIdsByEmail['demo.evan@campus.local'],
        'declined'
    );

    upsertTransactionChangeRequest(
        $pdo,
        $tx2,
        $orgIdsByName['Demo Computing Society'],
        $userIdsByEmail['demo.aira@campus.local'],
        'update',
        'pending',
        'expense',
        3000.00,
        'Updated hackathon materials budget',
        $now->modify('-7 days')->format('Y-m-d'),
        null
    );
    upsertTransactionChangeRequest(
        $pdo,
        $tx4,
        $orgIdsByName['Demo Arts Collective'],
        $userIdsByEmail['demo.mia@campus.local'],
        'delete',
        'approved',
        null,
        null,
        null,
        null,
        'Approved for testing of processed requests.'
    );
    upsertTransactionChangeRequest(
        $pdo,
        $tx6,
        $orgIdsByName['Demo Robotics Club'],
        $userIdsByEmail['demo.kai@campus.local'],
        'update',
        'rejected',
        'expense',
        7200.00,
        'Revised robotics components cost',
        $now->modify('-4 days')->format('Y-m-d'),
        'Rejected for over budget in test scenario.'
    );

    $pdo->commit();

    echo "Dummy data seeded successfully." . PHP_EOL;
    echo "Seed login password for seeded users: SeedPass123!" . PHP_EOL;
    echo "Seeded users: " . count($accounts) . PHP_EOL;
    echo "Seeded organizations: " . count($orgs) . PHP_EOL;
    $seedTransactionCount = (int) $pdo->query("SELECT COUNT(*) FROM financial_transactions WHERE description LIKE '%seed income' OR description LIKE '%seed expense' OR description IN ('Membership dues collection','Hackathon materials','Event sponsorship','Stage and lighting rental','Fundraising proceeds','Sensor and controller kits')")->fetchColumn();
    echo "Seeded transactions: " . $seedTransactionCount . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

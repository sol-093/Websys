<?php

declare(strict_types=1);

require __DIR__ . '/../../src/core/db.php';
require __DIR__ . '/../../src/lib/organization.php';

$pdo = db();
$now = new DateTimeImmutable('now');

function findUserIdByEmail(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function upsertUser(PDO $pdo, string $name, string $email, string $role, string $password, ?string $program = null, ?int $yearLevel = null, ?string $section = null): int
{
    $existingId = findUserIdByEmail($pdo, $email);
    $institute = getInstituteForProgram($program);

    if ($existingId !== null) {
        $update = $pdo->prepare('UPDATE users SET name = ?, role = ?, password_hash = ?, institute = ?, program = ?, year_level = ?, section = ? WHERE id = ?');
        $update->execute([$name, $role, password_hash($password, PASSWORD_DEFAULT), $institute, $program, $yearLevel, $section, $existingId]);

        return $existingId;
    }

    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, institute, program, year_level, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $institute, $program, $yearLevel, $section]);

    return (int) $pdo->lastInsertId();
}

function findOrganizationIdByName(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM organizations WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function upsertOrganization(PDO $pdo, string $name, string $description, ?int $ownerId, string $orgCategory = 'collegewide', ?string $targetInstitute = null, ?string $targetProgram = null): int
{
    $existingId = findOrganizationIdByName($pdo, $name);

    if ($existingId !== null) {
        $update = $pdo->prepare('UPDATE organizations SET description = ?, owner_id = ?, org_category = ?, target_institute = ?, target_program = ? WHERE id = ?');
        $update->execute([$description, $ownerId, $orgCategory, $targetInstitute, $targetProgram, $existingId]);

        return $existingId;
    }

    $insert = $pdo->prepare('INSERT INTO organizations (name, description, owner_id, org_category, target_institute, target_program) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$name, $description, $ownerId, $orgCategory, $targetInstitute, $targetProgram]);

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
    $stmt = $pdo->query("SELECT id FROM financial_transactions WHERE description LIKE '[DEMO]%' OR description LIKE '% seed income' OR description LIKE '% seed expense' OR description LIKE 'Membership dues collection' OR description LIKE 'Workshop kits and snacks' OR description LIKE 'Community outreach sponsorship' OR description LIKE 'Outreach supplies and transport' OR description LIKE 'Skills fair sponsorship' OR description LIKE 'Site safety and survey kits' ");
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
        ['name' => 'Demo Admin', 'email' => 'demo.admin@campus.local', 'role' => 'admin', 'program' => null, 'year_level' => null, 'section' => null],
        ['name' => 'Grace Navarro', 'email' => 'demo.grace@campus.local', 'role' => 'owner', 'program' => 'BS Information Systems', 'year_level' => 4, 'section' => 'A'],
        ['name' => 'Marco Reyes', 'email' => 'demo.marco@campus.local', 'role' => 'owner', 'program' => 'BS Civil Engineering', 'year_level' => 4, 'section' => 'B'],
        ['name' => 'Lina Bautista', 'email' => 'demo.lina@campus.local', 'role' => 'owner', 'program' => 'BS Psychology', 'year_level' => 4, 'section' => 'A'],
        ['name' => 'Noel Santos', 'email' => 'demo.noel@campus.local', 'role' => 'owner', 'program' => 'BS Nursing', 'year_level' => 4, 'section' => 'C'],
        ['name' => 'Hazel Mendez', 'email' => 'demo.hazel@campus.local', 'role' => 'owner', 'program' => 'BS Midwifery', 'year_level' => 3, 'section' => 'A'],
        ['name' => 'Ariana Flores', 'email' => 'demo.ariana@campus.local', 'role' => 'owner', 'program' => 'BS Social Work', 'year_level' => 4, 'section' => 'A'],
        ['name' => 'Paolo Cruz', 'email' => 'demo.paolo@campus.local', 'role' => 'student', 'program' => 'BS Information Systems', 'year_level' => 2, 'section' => 'A'],
        ['name' => 'Mika Tan', 'email' => 'demo.mika@campus.local', 'role' => 'student', 'program' => 'BS Data Science', 'year_level' => 3, 'section' => 'B'],
        ['name' => 'Jasper Lim', 'email' => 'demo.jasper@campus.local', 'role' => 'student', 'program' => 'BS Computer Science', 'year_level' => 1, 'section' => 'C'],
        ['name' => 'Camille Dela Rosa', 'email' => 'demo.camille@campus.local', 'role' => 'student', 'program' => 'BS Civil Engineering', 'year_level' => 2, 'section' => 'A'],
        ['name' => 'Nina Ramos', 'email' => 'demo.nina@campus.local', 'role' => 'student', 'program' => 'BS Psychology', 'year_level' => 2, 'section' => 'B'],
        ['name' => 'Iris Mercado', 'email' => 'demo.iris@campus.local', 'role' => 'student', 'program' => 'BS Nursing', 'year_level' => 3, 'section' => 'A'],
        ['name' => 'Benj Ortiz', 'email' => 'demo.benj@campus.local', 'role' => 'student', 'program' => 'BS Midwifery', 'year_level' => 2, 'section' => 'B'],
        ['name' => 'Sofia Palma', 'email' => 'demo.sofia@campus.local', 'role' => 'student', 'program' => 'BS Social Work', 'year_level' => 1, 'section' => 'A'],
        ['name' => 'Troy Ventura', 'email' => 'demo.troy@campus.local', 'role' => 'student', 'program' => 'BS Information Systems', 'year_level' => 1, 'section' => 'B'],
        ['name' => 'Lea Soriano', 'email' => 'demo.lea@campus.local', 'role' => 'student', 'program' => 'BS Psychology', 'year_level' => 1, 'section' => 'C'],
        ['name' => 'Ramon Delgado', 'email' => 'demo.ramon@campus.local', 'role' => 'student', 'program' => 'BS Civil Engineering', 'year_level' => 4, 'section' => 'A'],
        ['name' => 'Mara Escueta', 'email' => 'demo.mara@campus.local', 'role' => 'student', 'program' => 'BS Nursing', 'year_level' => 1, 'section' => 'B'],
        ['name' => 'Eli Castro', 'email' => 'demo.eli@campus.local', 'role' => 'student', 'program' => 'BS Data Science', 'year_level' => 4, 'section' => 'A'],
        ['name' => 'Jade Molina', 'email' => 'demo.jade@campus.local', 'role' => 'student', 'program' => 'BS Social Work', 'year_level' => 2, 'section' => 'B'],
        ['name' => 'Owen Pineda', 'email' => 'demo.owen@campus.local', 'role' => 'student', 'program' => 'BS Computer Science', 'year_level' => 2, 'section' => 'A'],
        ['name' => 'Legacy Student', 'email' => 'demo.legacy@campus.local', 'role' => 'student', 'program' => null, 'year_level' => null, 'section' => null],
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
            'SeedPass123!',
            $account['program'],
            $account['year_level'],
            $account['section']
        );
    }

    $orgs = [
        [
            'name' => 'Horizon Computing Guild',
            'description' => 'Computing student association for coding clinics, UI demos, and peer mentoring.',
            'owner_email' => 'demo.grace@campus.local',
            'org_category' => 'institutewide',
            'target_institute' => 'Institute of Computing and Digital Innovations',
            'target_program' => null,
        ],
        [
            'name' => 'Code Atelier Society',
            'description' => 'Information systems guild for app builds, systems analysis, and product demos.',
            'owner_email' => 'demo.grace@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Computing and Digital Innovations',
            'target_program' => 'BS Information Systems',
        ],
        [
            'name' => 'Data & AI Society',
            'description' => 'Data science group for analytics workshops, machine learning labs, and showcase talks.',
            'owner_email' => 'demo.grace@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Computing and Digital Innovations',
            'target_program' => 'BS Data Science',
        ],
        [
            'name' => 'Software Engineering Circle',
            'description' => 'Peer group for software architecture, testing practices, and agile retrospectives.',
            'owner_email' => 'demo.grace@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Computing and Digital Innovations',
            'target_program' => 'BS Computer Science',
        ],
        [
            'name' => 'Engineering Leaders Association',
            'description' => 'Institute-wide engineering forum for leadership, design reviews, and field exposure.',
            'owner_email' => 'demo.marco@campus.local',
            'org_category' => 'institutewide',
            'target_institute' => 'Institute of Engineering',
            'target_program' => null,
        ],
        [
            'name' => 'Civil Engineers Forum',
            'description' => 'Program-based group for surveying, design critiques, and site visits.',
            'owner_email' => 'demo.marco@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Engineering',
            'target_program' => 'BS Civil Engineering',
        ],
        [
            'name' => 'Wellness and Care Council',
            'description' => 'Institute-wide nursing group for wellness drives, leadership, and service events.',
            'owner_email' => 'demo.noel@campus.local',
            'org_category' => 'institutewide',
            'target_institute' => 'Institute of Nursing',
            'target_program' => null,
        ],
        [
            'name' => 'Nursing Student Alliance',
            'description' => 'Program-based nursing circle for clinical skills reviews and volunteer care.',
            'owner_email' => 'demo.noel@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Nursing',
            'target_program' => 'BS Nursing',
        ],
        [
            'name' => 'Midwifery Scholars Network',
            'description' => 'Program-based group for maternal health learning, case studies, and outreach.',
            'owner_email' => 'demo.hazel@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Midwifery',
            'target_program' => 'BS Midwifery',
        ],
        [
            'name' => 'Behavioral Science Forum',
            'description' => 'Institute-wide group for psychology, counseling, and behavioral research.',
            'owner_email' => 'demo.lina@campus.local',
            'org_category' => 'institutewide',
            'target_institute' => 'Institute of Behavioral Science',
            'target_program' => null,
        ],
        [
            'name' => 'Psychology Peer Group',
            'description' => 'Program-based psychology circle for case discussions and peer support sessions.',
            'owner_email' => 'demo.lina@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Behavioral Science',
            'target_program' => 'BS Psychology',
        ],
        [
            'name' => 'Community Service Alliance',
            'description' => 'Collegewide volunteering network for outreach, relief drives, and campus service.',
            'owner_email' => 'demo.ariana@campus.local',
            'org_category' => 'collegewide',
            'target_institute' => null,
            'target_program' => null,
        ],
        [
            'name' => 'Student Activities Assembly',
            'description' => 'Collegewide council coordinating events, fairs, and student participation.',
            'owner_email' => 'demo.ariana@campus.local',
            'org_category' => 'collegewide',
            'target_institute' => null,
            'target_program' => null,
        ],
        [
            'name' => 'Science and Research Forum',
            'description' => 'Institute-wide group for research methods, lab showcases, and seminar talks.',
            'owner_email' => 'demo.ariana@campus.local',
            'org_category' => 'institutewide',
            'target_institute' => 'Institute of Science and Mathematics',
            'target_program' => null,
        ],
        [
            'name' => 'Social Work Advocates Circle',
            'description' => 'Program-based group for advocacy, case work, and community immersion.',
            'owner_email' => 'demo.ariana@campus.local',
            'org_category' => 'program_based',
            'target_institute' => 'Institute of Science and Mathematics',
            'target_program' => 'BS Social Work',
        ],
        [
            'name' => 'Media and Creators Guild',
            'description' => 'Collegewide group for photography, content production, and design support.',
            'owner_email' => 'demo.lina@campus.local',
            'org_category' => 'collegewide',
            'target_institute' => null,
            'target_program' => null,
        ],
    ];

    $orgIdsByName = [];
    foreach ($orgs as $organization) {
        $normalizedName = normalizeSeedName((string) $organization['name']);
        $normalizedDescription = trim((string) $organization['description']);
        $ownerId = $userIdsByEmail[$organization['owner_email']] ?? null;
        $orgIdsByName[$organization['name']] = upsertOrganization(
            $pdo,
            $normalizedName,
            $normalizedDescription,
            $ownerId,
            (string) $organization['org_category'],
            $organization['target_institute'],
            $organization['target_program']
        );
        $orgIdsByName[$normalizedName] = $orgIdsByName[$organization['name']];
    }

    $membershipMatrix = [
        'Horizon Computing Guild' => [
            'demo.grace@campus.local',
            'demo.paolo@campus.local',
            'demo.mika@campus.local',
            'demo.jasper@campus.local',
            'demo.troy@campus.local',
            'demo.eli@campus.local',
            'demo.owen@campus.local',
            'demo.legacy@campus.local',
        ],
        'Code Atelier Society' => [
            'demo.grace@campus.local',
            'demo.paolo@campus.local',
            'demo.troy@campus.local',
        ],
        'Data & AI Society' => [
            'demo.grace@campus.local',
            'demo.mika@campus.local',
            'demo.eli@campus.local',
        ],
        'Software Engineering Circle' => [
            'demo.grace@campus.local',
            'demo.jasper@campus.local',
            'demo.owen@campus.local',
        ],
        'Engineering Leaders Association' => [
            'demo.marco@campus.local',
            'demo.camille@campus.local',
            'demo.ramon@campus.local',
        ],
        'Civil Engineers Forum' => [
            'demo.marco@campus.local',
            'demo.camille@campus.local',
            'demo.ramon@campus.local',
        ],
        'Wellness and Care Council' => [
            'demo.noel@campus.local',
            'demo.iris@campus.local',
            'demo.mara@campus.local',
        ],
        'Nursing Student Alliance' => [
            'demo.noel@campus.local',
            'demo.iris@campus.local',
            'demo.mara@campus.local',
        ],
        'Midwifery Scholars Network' => [
            'demo.hazel@campus.local',
            'demo.benj@campus.local',
        ],
        'Behavioral Science Forum' => [
            'demo.lina@campus.local',
            'demo.nina@campus.local',
            'demo.lea@campus.local',
        ],
        'Psychology Peer Group' => [
            'demo.lina@campus.local',
            'demo.nina@campus.local',
            'demo.lea@campus.local',
        ],
        'Community Service Alliance' => [
            'demo.ariana@campus.local',
            'demo.paolo@campus.local',
            'demo.mika@campus.local',
            'demo.camille@campus.local',
            'demo.nina@campus.local',
            'demo.iris@campus.local',
            'demo.benj@campus.local',
            'demo.sofia@campus.local',
            'demo.troy@campus.local',
            'demo.lea@campus.local',
            'demo.ramon@campus.local',
            'demo.mara@campus.local',
            'demo.eli@campus.local',
            'demo.jade@campus.local',
            'demo.owen@campus.local',
        ],
        'Student Activities Assembly' => [
            'demo.ariana@campus.local',
            'demo.paolo@campus.local',
            'demo.mika@campus.local',
            'demo.jasper@campus.local',
            'demo.camille@campus.local',
            'demo.nina@campus.local',
            'demo.iris@campus.local',
            'demo.benj@campus.local',
            'demo.sofia@campus.local',
            'demo.troy@campus.local',
            'demo.lea@campus.local',
            'demo.ramon@campus.local',
            'demo.mara@campus.local',
            'demo.eli@campus.local',
            'demo.jade@campus.local',
            'demo.owen@campus.local',
        ],
        'Science and Research Forum' => [
            'demo.ariana@campus.local',
            'demo.sofia@campus.local',
            'demo.jade@campus.local',
        ],
        'Social Work Advocates Circle' => [
            'demo.ariana@campus.local',
            'demo.sofia@campus.local',
            'demo.jade@campus.local',
        ],
        'Media and Creators Guild' => [
            'demo.grace@campus.local',
            'demo.lina@campus.local',
            'demo.marco@campus.local',
            'demo.nina@campus.local',
            'demo.owen@campus.local',
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
        $orgIdsByName['Horizon Computing Guild'],
        'Welcome to the Horizon Computing Guild',
        'Kickoff meeting, code clinic, and peer mentoring schedule for new and continuing members.'
    );
    ensureAnnouncement(
        $pdo,
        $orgIdsByName['Engineering Leaders Association'],
        'Engineering field exposure schedule',
        'Site visit reminders, safety notes, and clearance deadlines for the next activity.'
    );
    ensureAnnouncement(
        $pdo,
        $orgIdsByName['Wellness and Care Council'],
        'Clinical duty briefing',
        'Updated volunteer roster, reporting time, and uniform reminders for the next rotation.'
    );
    ensureAnnouncement(
        $pdo,
        $orgIdsByName['Community Service Alliance'],
        'Campus outreach drive',
        'Collection points, transport schedule, and packing instructions for the weekend outreach.'
    );

    foreach ($orgs as $organization) {
        $orgName = $organization['name'];
        if (in_array($orgName, ['Horizon Computing Guild', 'Engineering Leaders Association', 'Wellness and Care Council', 'Community Service Alliance'], true)) {
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
        $orgIdsByName['Horizon Computing Guild'],
        'income',
        14250.00,
        'Membership dues collection',
        '2025-12-12'
    );
    $tx2 = ensureTransaction(
        $pdo,
        $orgIdsByName['Horizon Computing Guild'],
        'expense',
        3850.50,
        'Workshop kits and snacks',
        '2026-01-09'
    );
    $tx3 = ensureTransaction(
        $pdo,
        $orgIdsByName['Community Service Alliance'],
        'income',
        9800.00,
        'Community outreach sponsorship',
        '2026-01-18'
    );
    $tx4 = ensureTransaction(
        $pdo,
        $orgIdsByName['Community Service Alliance'],
        'expense',
        4200.75,
        'Outreach supplies and transport',
        '2026-02-01'
    );
    $tx5 = ensureTransaction(
        $pdo,
        $orgIdsByName['Engineering Leaders Association'],
        'income',
        16800.00,
        'Skills fair sponsorship',
        '2025-11-24'
    );
    $tx6 = ensureTransaction(
        $pdo,
        $orgIdsByName['Engineering Leaders Association'],
        'expense',
        7300.00,
        'Site safety and survey kits',
        '2026-02-23'
    );

    $extraIncomeDates = ['2026-01-05', '2026-01-06', '2026-01-12', '2026-01-13', '2026-01-19', '2026-01-20', '2026-01-26', '2026-01-27', '2026-02-02', '2026-02-03', '2026-02-09', '2026-02-10', '2026-02-16'];
    $extraExpenseDates = ['2026-01-08', '2026-01-09', '2026-01-15', '2026-01-16', '2026-01-22', '2026-01-23', '2026-01-29', '2026-01-30', '2026-02-05', '2026-02-06', '2026-02-11', '2026-02-18', '2026-02-25'];
    $extraOrgNames = [
        'Code Atelier Society',
        'Data & AI Society',
        'Software Engineering Circle',
        'Civil Engineers Forum',
        'Wellness and Care Council',
        'Nursing Student Alliance',
        'Midwifery Scholars Network',
        'Behavioral Science Forum',
        'Psychology Peer Group',
        'Science and Research Forum',
        'Social Work Advocates Circle',
        'Student Activities Assembly',
        'Media and Creators Guild',
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
        $orgIdsByName['Horizon Computing Guild'],
        $userIdsByEmail['demo.paolo@campus.local'],
        'accepted'
    );
    upsertOwnerAssignment(
        $pdo,
        $orgIdsByName['Engineering Leaders Association'],
        $userIdsByEmail['demo.camille@campus.local'],
        'pending'
    );
    upsertOwnerAssignment(
        $pdo,
        $orgIdsByName['Wellness and Care Council'],
        $userIdsByEmail['demo.mara@campus.local'],
        'declined'
    );
    upsertOwnerAssignment(
        $pdo,
        $orgIdsByName['Psychology Peer Group'],
        $userIdsByEmail['demo.lea@campus.local'],
        'accepted'
    );

    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Code Atelier Society'],
        $userIdsByEmail['demo.paolo@campus.local'],
        'approved'
    );
    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Data & AI Society'],
        $userIdsByEmail['demo.mika@campus.local'],
        'pending'
    );
    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Civil Engineers Forum'],
        $userIdsByEmail['demo.ramon@campus.local'],
        'declined'
    );
    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Wellness and Care Council'],
        $userIdsByEmail['demo.mara@campus.local'],
        'approved'
    );
    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Social Work Advocates Circle'],
        $userIdsByEmail['demo.jade@campus.local'],
        'pending'
    );
    upsertJoinRequest(
        $pdo,
        $orgIdsByName['Community Service Alliance'],
        $userIdsByEmail['demo.legacy@campus.local'],
        'approved'
    );

    upsertTransactionChangeRequest(
        $pdo,
        $tx2,
        $orgIdsByName['Horizon Computing Guild'],
        $userIdsByEmail['demo.paolo@campus.local'],
        'update',
        'pending',
        'expense',
        3000.00,
        'Updated workshop budget',
        $now->modify('-7 days')->format('Y-m-d'),
        null
    );
    upsertTransactionChangeRequest(
        $pdo,
        $tx4,
        $orgIdsByName['Community Service Alliance'],
        $userIdsByEmail['demo.mika@campus.local'],
        'delete',
        'approved',
        null,
        null,
        null,
        null,
        'Approved for the seeded review workflow.'
    );
    upsertTransactionChangeRequest(
        $pdo,
        $tx6,
        $orgIdsByName['Engineering Leaders Association'],
        $userIdsByEmail['demo.camille@campus.local'],
        'update',
        'rejected',
        'expense',
        7200.00,
        'Revised field kit cost',
        $now->modify('-4 days')->format('Y-m-d'),
        'Rejected because the request exceeded the current budget ceiling.'
    );

    $pdo->commit();

    echo "Dummy data seeded successfully." . PHP_EOL;
    echo "Seed login password for seeded users: SeedPass123!" . PHP_EOL;
    echo "Seeded users: " . count($accounts) . PHP_EOL;
    echo "Seeded organizations: " . count($orgs) . PHP_EOL;
    $seedTransactionCount = (int) $pdo->query("SELECT COUNT(*) FROM financial_transactions WHERE description LIKE '%seed income' OR description LIKE '%seed expense' OR description IN ('Membership dues collection','Workshop kits and snacks','Community outreach sponsorship','Outreach supplies and transport','Skills fair sponsorship','Site safety and survey kits')")->fetchColumn();
    echo "Seeded transactions: " . $seedTransactionCount . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

require __DIR__ . '/../../includes/core/db.php';

$pdo = db();
$now = new DateTimeImmutable('2026-05-08 09:00:00');

function budgetSeedFindUserId(PDO $pdo, string $where, array $params = []): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE {$where} ORDER BY id ASC LIMIT 1");
    $stmt->execute($params);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function budgetSeedFindOrg(PDO $pdo, string $name): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, owner_id FROM organizations WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $org = $stmt->fetch();

    return is_array($org) ? $org : null;
}

function budgetSeedFirstOrg(PDO $pdo): ?array
{
    $stmt = $pdo->query('SELECT id, name, owner_id FROM organizations ORDER BY id ASC LIMIT 1');
    $org = $stmt->fetch();

    return is_array($org) ? $org : null;
}

function budgetSeedMemberOrOwner(PDO $pdo, int $organizationId, ?int $ownerId): ?int
{
    if ($ownerId !== null && $ownerId > 0) {
        return $ownerId;
    }

    $stmt = $pdo->prepare('SELECT user_id FROM organization_members WHERE organization_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$organizationId]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function budgetSeedReset(PDO $pdo, array $scenarioKeys): void
{
    if ($scenarioKeys === []) {
        return;
    }

    $conditions = [];
    $params = [];
    foreach ($scenarioKeys as $key) {
        $conditions[] = '(organization_id = ? AND title = ?)';
        $params[] = $key['organization_id'];
        $params[] = $key['title'];
    }

    $budgetStmt = $pdo->prepare('SELECT id FROM budgets WHERE ' . implode(' OR ', $conditions));
    $budgetStmt->execute($params);
    $budgetIds = array_map(static fn($id): int => (int) $id, $budgetStmt->fetchAll(PDO::FETCH_COLUMN));
    if ($budgetIds === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($budgetIds), '?'));
    $requestStmt = $pdo->prepare("SELECT id FROM expense_requests WHERE budget_id IN ({$placeholders})");
    $requestStmt->execute($budgetIds);
    $requestIds = array_map(static fn($id): int => (int) $id, $requestStmt->fetchAll(PDO::FETCH_COLUMN));

    if ($requestIds !== []) {
        $requestPlaceholders = implode(',', array_fill(0, count($requestIds), '?'));
        $pdo->prepare("UPDATE expense_requests SET transaction_id = NULL WHERE id IN ({$requestPlaceholders})")->execute($requestIds);
        $pdo->prepare("DELETE FROM financial_transactions WHERE expense_request_id IN ({$requestPlaceholders})")->execute($requestIds);
    }

    $pdo->prepare("DELETE FROM budgets WHERE id IN ({$placeholders})")->execute($budgetIds);
}

function budgetSeedCreateBudget(PDO $pdo, array $scenario, int $createdBy): int
{
    $stmt = $pdo->prepare('INSERT INTO budgets (organization_id, created_by, title, period_start, period_end, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        (int) $scenario['organization_id'],
        $createdBy,
        (string) $scenario['title'],
        (string) $scenario['period_start'],
        (string) $scenario['period_end'],
        (float) $scenario['total_amount'],
        (string) $scenario['status'],
    ]);

    return (int) $pdo->lastInsertId();
}

function budgetSeedCreateLine(PDO $pdo, int $budgetId, array $line): int
{
    $stmt = $pdo->prepare('INSERT INTO budget_line_items (budget_id, category_name, description, allocated_amount, spent_amount) VALUES (?, ?, ?, ?, 0)');
    $stmt->execute([
        $budgetId,
        (string) $line['category'],
        (string) $line['description'],
        (float) $line['allocated'],
    ]);

    return (int) $pdo->lastInsertId();
}

function budgetSeedCreateRequest(PDO $pdo, array $context, array $request, DateTimeImmutable $now): void
{
    $lineId = (int) $context['line_ids'][$request['line']];
    $status = (string) $request['status'];
    $reviewedBy = $status === 'pending' ? null : (int) $context['admin_id'];
    $reviewedAt = $status === 'pending' ? null : $now->modify('-' . (int) ($request['reviewed_days_ago'] ?? 2) . ' days')->format('Y-m-d H:i:s');
    $adminNote = $status === 'rejected' ? (string) ($request['admin_note'] ?? 'Rejected during seeded review.') : null;

    $insertRequest = $pdo->prepare(
        'INSERT INTO expense_requests (organization_id, budget_id, budget_line_item_id, requested_by, amount, description, receipt_path, status, admin_note, reviewed_by, reviewed_at, transaction_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)'
    );
    $createdAt = $now->modify('-' . (int) ($request['created_days_ago'] ?? 7) . ' days')->format('Y-m-d H:i:s');
    $insertRequest->execute([
        (int) $context['organization_id'],
        (int) $context['budget_id'],
        $lineId,
        (int) $context['requester_id'],
        (float) $request['amount'],
        (string) $request['description'],
        $request['receipt_path'] ?? null,
        $status,
        $adminNote,
        $reviewedBy,
        $reviewedAt,
        $createdAt,
        $reviewedAt ?? $createdAt,
    ]);
    $requestId = (int) $pdo->lastInsertId();

    if ($status !== 'approved') {
        return;
    }

    $transactionDate = $now->modify('-' . (int) ($request['transaction_days_ago'] ?? 1) . ' days')->format('Y-m-d');
    $txStmt = $pdo->prepare(
        'INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path, expense_request_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $txStmt->execute([
        (int) $context['organization_id'],
        'expense',
        (float) $request['amount'],
        (string) $request['description'],
        $transactionDate,
        $request['receipt_path'] ?? null,
        $requestId,
        $reviewedAt ?? $createdAt,
    ]);
    $transactionId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE expense_requests SET transaction_id = ? WHERE id = ?')->execute([$transactionId, $requestId]);
    $pdo->prepare('UPDATE budget_line_items SET spent_amount = spent_amount + ? WHERE id = ?')->execute([(float) $request['amount'], $lineId]);
}

$adminId = budgetSeedFindUserId($pdo, "role = 'admin'");
if ($adminId === null) {
    fwrite(STDERR, "BudgetFlow seeding requires at least one admin user. Run seed_dummy_data.php first.\n");
    exit(1);
}

$scenarioTemplates = [
    'Horizon Computing Guild' => [
        'title' => 'AY 2025-2026 Operating Budget',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'total_amount' => 62000.00,
        'status' => 'active',
        'lines' => [
            ['key' => 'workshops', 'category' => 'Workshops and training', 'description' => 'Speaker honoraria, lab materials, and snacks for monthly technical clinics.', 'allocated' => 18000.00],
            ['key' => 'equipment', 'category' => 'Shared equipment', 'description' => 'Peripheral replacements, extension cords, and demo hardware for lab sessions.', 'allocated' => 16000.00],
            ['key' => 'outreach', 'category' => 'Peer mentoring outreach', 'description' => 'Orientation booths, mentoring kits, and printed onboarding guides.', 'allocated' => 12000.00],
            ['key' => 'operations', 'category' => 'Operations reserve', 'description' => 'Forms, IDs, supplies, and small logistics expenses.', 'allocated' => 9000.00],
        ],
        'requests' => [
            ['line' => 'workshops', 'amount' => 4850.00, 'description' => 'JavaScript clinic speaker honorarium and snacks', 'status' => 'approved', 'created_days_ago' => 21, 'reviewed_days_ago' => 19, 'transaction_days_ago' => 18],
            ['line' => 'equipment', 'amount' => 7250.00, 'description' => 'HDMI adapters and demo keyboards for lab activities', 'status' => 'approved', 'created_days_ago' => 16, 'reviewed_days_ago' => 14, 'transaction_days_ago' => 13],
            ['line' => 'outreach', 'amount' => 3200.00, 'description' => 'Freshman mentoring booth print materials', 'status' => 'pending', 'created_days_ago' => 2],
            ['line' => 'operations', 'amount' => 2800.00, 'description' => 'Premium event backdrop request', 'status' => 'rejected', 'created_days_ago' => 11, 'reviewed_days_ago' => 9, 'admin_note' => 'Use the reusable backdrop from the activities office.'],
        ],
    ],
    'Community Service Alliance' => [
        'title' => 'Q2 2026 Outreach Budget',
        'period_start' => '2026-04-01',
        'period_end' => '2026-06-30',
        'total_amount' => 54000.00,
        'status' => 'active',
        'lines' => [
            ['key' => 'relief', 'category' => 'Relief packs', 'description' => 'Rice, canned goods, hygiene kits, and packing materials.', 'allocated' => 22000.00],
            ['key' => 'transport', 'category' => 'Transport and logistics', 'description' => 'Vehicle rental, fuel support, and delivery coordination.', 'allocated' => 13500.00],
            ['key' => 'volunteer', 'category' => 'Volunteer meals', 'description' => 'Packed meals and drinking water during field activities.', 'allocated' => 9000.00],
            ['key' => 'materials', 'category' => 'Teaching materials', 'description' => 'Printed modules and supplies for partner community sessions.', 'allocated' => 7000.00],
        ],
        'requests' => [
            ['line' => 'relief', 'amount' => 9100.00, 'description' => 'Initial grocery purchase for 120 relief packs', 'status' => 'approved', 'created_days_ago' => 18, 'reviewed_days_ago' => 17, 'transaction_days_ago' => 16],
            ['line' => 'transport', 'amount' => 5800.00, 'description' => 'Van rental for Barangay San Isidro visit', 'status' => 'approved', 'created_days_ago' => 9, 'reviewed_days_ago' => 7, 'transaction_days_ago' => 6],
            ['line' => 'materials', 'amount' => 2600.00, 'description' => 'Printing of child safety activity sheets', 'status' => 'pending', 'created_days_ago' => 1],
        ],
    ],
    'Engineering Leaders Association' => [
        'title' => '2026 Field Exposure Budget',
        'period_start' => '2026-02-01',
        'period_end' => '2026-08-31',
        'total_amount' => 68500.00,
        'status' => 'active',
        'lines' => [
            ['key' => 'site', 'category' => 'Site visit logistics', 'description' => 'Transport, permits, and coordination expenses for approved visits.', 'allocated' => 26000.00],
            ['key' => 'safety', 'category' => 'Safety equipment', 'description' => 'Hard hats, vests, gloves, and first-aid materials.', 'allocated' => 18500.00],
            ['key' => 'seminar', 'category' => 'Technical seminars', 'description' => 'Guest engineer honoraria and seminar materials.', 'allocated' => 16000.00],
            ['key' => 'documentation', 'category' => 'Documentation', 'description' => 'Printing, certificates, and post-event documentation.', 'allocated' => 5000.00],
        ],
        'requests' => [
            ['line' => 'site', 'amount' => 11800.00, 'description' => 'Bus reservation for flood-control site visit', 'status' => 'approved', 'created_days_ago' => 24, 'reviewed_days_ago' => 22, 'transaction_days_ago' => 21],
            ['line' => 'safety', 'amount' => 6400.00, 'description' => 'Safety vests and hard hats for field exposure', 'status' => 'approved', 'created_days_ago' => 15, 'reviewed_days_ago' => 13, 'transaction_days_ago' => 12],
            ['line' => 'seminar', 'amount' => 4500.00, 'description' => 'Honorarium for structural design talk', 'status' => 'pending', 'created_days_ago' => 3],
        ],
    ],
    'Wellness and Care Council' => [
        'title' => 'First Semester Wellness Budget',
        'period_start' => '2025-08-01',
        'period_end' => '2025-12-31',
        'total_amount' => 39000.00,
        'status' => 'closed',
        'lines' => [
            ['key' => 'screening', 'category' => 'Health screening', 'description' => 'Basic supplies for campus wellness monitoring.', 'allocated' => 15500.00],
            ['key' => 'training', 'category' => 'Volunteer training', 'description' => 'Skills review materials and simulation supplies.', 'allocated' => 12500.00],
            ['key' => 'campaign', 'category' => 'Awareness campaign', 'description' => 'Posters, booth materials, and wellness handouts.', 'allocated' => 9000.00],
        ],
        'requests' => [
            ['line' => 'screening', 'amount' => 8700.00, 'description' => 'Blood pressure cuffs and disposable supplies', 'status' => 'approved', 'created_days_ago' => 151, 'reviewed_days_ago' => 149, 'transaction_days_ago' => 148],
            ['line' => 'training', 'amount' => 5200.00, 'description' => 'Volunteer skills review kits', 'status' => 'approved', 'created_days_ago' => 132, 'reviewed_days_ago' => 130, 'transaction_days_ago' => 129],
            ['line' => 'campaign', 'amount' => 3100.00, 'description' => 'Mental health awareness poster printing', 'status' => 'approved', 'created_days_ago' => 117, 'reviewed_days_ago' => 116, 'transaction_days_ago' => 115],
        ],
    ],
    'Code Atelier Society' => [
        'title' => 'Draft App Showcase Budget',
        'period_start' => '2026-07-01',
        'period_end' => '2026-09-30',
        'total_amount' => 31500.00,
        'status' => 'draft',
        'lines' => [
            ['key' => 'venue', 'category' => 'Showcase venue setup', 'description' => 'Booth layout, extension cords, and signage.', 'allocated' => 12000.00],
            ['key' => 'awards', 'category' => 'Awards and certificates', 'description' => 'Recognition tokens and finalist certificates.', 'allocated' => 6500.00],
            ['key' => 'demo', 'category' => 'Demo support', 'description' => 'Testing devices and presentation materials.', 'allocated' => 8000.00],
        ],
        'requests' => [],
    ],
];

$availableScenarios = [];
foreach ($scenarioTemplates as $orgName => $scenario) {
    $org = budgetSeedFindOrg($pdo, $orgName);
    if (!$org) {
        continue;
    }

    $scenario['organization_id'] = (int) $org['id'];
    $scenario['organization_name'] = (string) $org['name'];
    $scenario['owner_id'] = budgetSeedMemberOrOwner($pdo, (int) $org['id'], isset($org['owner_id']) ? (int) $org['owner_id'] : null);
    if ($scenario['owner_id'] === null) {
        continue;
    }

    $availableScenarios[] = $scenario;
}

if ($availableScenarios === []) {
    $fallbackOrg = budgetSeedFirstOrg($pdo);
    $fallbackOwnerId = $fallbackOrg ? budgetSeedMemberOrOwner($pdo, (int) $fallbackOrg['id'], isset($fallbackOrg['owner_id']) ? (int) $fallbackOrg['owner_id'] : null) : null;
    if (!$fallbackOrg || $fallbackOwnerId === null) {
        fwrite(STDERR, "BudgetFlow seeding requires organizations with owners or members. Run seed_dummy_data.php first.\n");
        exit(1);
    }

    $fallback = $scenarioTemplates['Horizon Computing Guild'];
    $fallback['title'] = 'AY 2025-2026 Operating Budget';
    $fallback['organization_id'] = (int) $fallbackOrg['id'];
    $fallback['organization_name'] = (string) $fallbackOrg['name'];
    $fallback['owner_id'] = $fallbackOwnerId;
    $availableScenarios[] = $fallback;
}

$pdo->beginTransaction();

try {
    $scenarioKeys = array_map(static fn(array $scenario): array => [
        'organization_id' => (int) $scenario['organization_id'],
        'title' => (string) $scenario['title'],
    ], $availableScenarios);
    budgetSeedReset($pdo, $scenarioKeys);

    $budgetCount = 0;
    $lineCount = 0;
    $requestCount = 0;
    $approvedCount = 0;

    foreach ($availableScenarios as $scenario) {
        $budgetId = budgetSeedCreateBudget($pdo, $scenario, (int) $scenario['owner_id']);
        $budgetCount++;

        $lineIds = [];
        foreach ($scenario['lines'] as $line) {
            $lineIds[(string) $line['key']] = budgetSeedCreateLine($pdo, $budgetId, $line);
            $lineCount++;
        }

        $context = [
            'budget_id' => $budgetId,
            'organization_id' => (int) $scenario['organization_id'],
            'requester_id' => (int) $scenario['owner_id'],
            'admin_id' => $adminId,
            'line_ids' => $lineIds,
        ];

        foreach ($scenario['requests'] as $request) {
            budgetSeedCreateRequest($pdo, $context, $request, $now);
            $requestCount++;
            if ((string) $request['status'] === 'approved') {
                $approvedCount++;
            }
        }
    }

    $pdo->commit();

    echo "BudgetFlow dummy data seeded successfully." . PHP_EOL;
    echo "Budgets: {$budgetCount}" . PHP_EOL;
    echo "Budget line items: {$lineCount}" . PHP_EOL;
    echo "Expense requests: {$requestCount}" . PHP_EOL;
    echo "Approved linked transactions: {$approvedCount}" . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'BudgetFlow seeding failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

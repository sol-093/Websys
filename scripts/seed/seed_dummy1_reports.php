<?php

declare(strict_types=1);

require __DIR__ . '/../../src/core/db.php';

$pdo = db();

$preferredOrgNames = [
    'Innovation Lab Council',
    'Cybersecurity Student Network',
    'Human Behavior Research Circle',
    'Campus Events Production Team',
];

$placeholders = implode(',', array_fill(0, count($preferredOrgNames), '?'));
$stmt = $pdo->prepare("SELECT id, name FROM organizations WHERE name IN ($placeholders) ORDER BY name ASC");
$stmt->execute($preferredOrgNames);
$organizations = $stmt->fetchAll();

if (count($organizations) === 0) {
    fwrite(STDOUT, "No extra organizations found. Run scripts/seed/seed_extra_dummy_data.php first.\n");
    exit(0);
}

function extraReportUpsertTransaction(PDO $pdo, int $organizationId, string $type, float $amount, string $description, string $date): void
{
    $stmt = $pdo->prepare('SELECT id FROM financial_transactions WHERE organization_id = ? AND description = ? AND transaction_date = ? LIMIT 1');
    $stmt->execute([$organizationId, $description, $date]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        $update = $pdo->prepare('UPDATE financial_transactions SET type = ?, amount = ? WHERE id = ?');
        $update->execute([$type, number_format($amount, 2, '.', ''), (int) $existingId]);
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path)
         VALUES (?, ?, ?, ?, ?, NULL)'
    );
    $insert->execute([$organizationId, $type, number_format($amount, 2, '.', ''), $description, $date]);
}

$templates = [
    'Innovation Lab Council' => [
        ['income', 18500.00, '[EXTRA] Prototype fair sponsorship', '-5 months 4 days'],
        ['expense', 6420.75, '[EXTRA] Demo booth materials', '-5 months 12 days'],
        ['income', 9300.50, '[EXTRA] Innovation clinic registration', '-3 months 6 days'],
        ['expense', 3785.25, '[EXTRA] Mentors meal allowance', '-1 month 9 days'],
    ],
    'Cybersecurity Student Network' => [
        ['income', 12400.00, '[EXTRA] Security workshop ticket sales', '-4 months 8 days'],
        ['expense', 5100.00, '[EXTRA] Lab access and cloud credits', '-4 months 17 days'],
        ['income', 8700.00, '[EXTRA] CTF sponsorship package', '-2 months 5 days'],
        ['expense', 2950.50, '[EXTRA] Practice kit purchase', '-20 days'],
    ],
    'Human Behavior Research Circle' => [
        ['income', 7600.00, '[EXTRA] Research colloquium support', '-5 months 2 days'],
        ['expense', 2410.25, '[EXTRA] Survey printing and tokens', '-3 months 19 days'],
        ['income', 6800.00, '[EXTRA] Peer seminar collection', '-2 months 11 days'],
        ['expense', 1985.75, '[EXTRA] Documentation supplies', '-13 days'],
    ],
    'Campus Events Production Team' => [
        ['income', 15200.00, '[EXTRA] Campus production service fee', '-4 months 1 day'],
        ['expense', 8200.00, '[EXTRA] Lights and audio rental', '-3 months 3 days'],
        ['income', 11150.25, '[EXTRA] Event registration desk support', '-1 month 18 days'],
        ['expense', 4550.50, '[EXTRA] Volunteer meals and IDs', '-7 days'],
    ],
];

$today = new DateTimeImmutable('today');
$rowsUpserted = 0;

$pdo->beginTransaction();

try {
    foreach ($organizations as $organization) {
        $orgName = (string) $organization['name'];
        $orgId = (int) $organization['id'];

        foreach ($templates[$orgName] ?? [] as [$type, $amount, $description, $relativeDate]) {
            $date = $today->modify((string) $relativeDate)->format('Y-m-d');
            extraReportUpsertTransaction($pdo, $orgId, (string) $type, (float) $amount, (string) $description, $date);
            $rowsUpserted++;
        }
    }

    $pdo->commit();

    fwrite(STDOUT, "Extra dummy reports seeded successfully.\n");
    fwrite(STDOUT, "Extra report rows upserted: {$rowsUpserted}\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Extra dummy report seeding failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

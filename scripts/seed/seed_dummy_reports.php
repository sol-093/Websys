<?php

declare(strict_types=1);

require __DIR__ . '/../../src/core/db.php';

$pdo = db();

$organizations = $pdo->query('SELECT id, name FROM organizations ORDER BY id ASC')->fetchAll();
if (count($organizations) === 0) {
    fwrite(STDOUT, "No organizations found. Create at least one organization first.\n");
    exit(0);
}

$incomeTemplates = [
    'Membership dues collection',
    'Fundraising event proceeds',
    'Sponsorship support',
    'Booth rental income',
    'Ticket sales revenue',
    'Merchandise sales',
];

$expenseTemplates = [
    'Venue and logistics payment',
    'Equipment and materials purchase',
    'Speaker and facilitator honorarium',
    'Marketing and promotion expense',
    'Transportation and delivery cost',
    'Administrative supply expense',
];

$insert = $pdo->prepare(
    'INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path)
     VALUES (?, ?, ?, ?, ?, NULL)'
);

$rowsInserted = 0;
$monthsBack = 6;
$rowsPerOrgPerMonth = 2;

$today = new DateTimeImmutable('today');

$pdo->beginTransaction();
try {
    foreach ($organizations as $org) {
        $orgId = (int) $org['id'];

        for ($m = 0; $m < $monthsBack; $m++) {
            $monthStart = $today->modify("-{$m} months")->modify('first day of this month');
            $monthEnd = $today->modify("-{$m} months")->modify('last day of this month');
            $maxDay = (int) $monthEnd->format('j');

            for ($i = 0; $i < $rowsPerOrgPerMonth; $i++) {
                $isIncome = (($i + $m) % 2) === 0;
                $type = $isIncome ? 'income' : 'expense';

                $template = $isIncome
                    ? $incomeTemplates[array_rand($incomeTemplates)]
                    : $expenseTemplates[array_rand($expenseTemplates)];

                $amount = $isIncome
                    ? random_int(2500, 15000) + (random_int(0, 99) / 100)
                    : random_int(1200, 9800) + (random_int(0, 99) / 100);

                $day = random_int(1, $maxDay);
                $date = $monthStart->setDate(
                    (int) $monthStart->format('Y'),
                    (int) $monthStart->format('m'),
                    $day
                )->format('Y-m-d');

                $description = sprintf('%s (%s)', $template, $org['name']);

                $insert->execute([
                    $orgId,
                    $type,
                    number_format($amount, 2, '.', ''),
                    $description,
                    $date,
                ]);

                $rowsInserted++;
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Failed to seed dummy reports: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Seeded {$rowsInserted} dummy financial reports across " . count($organizations) . " organization(s).\n");

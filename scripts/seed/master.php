<?php
declare(strict_types=1);

// Array of scripts to execute in order
$seeders = [
    //'unseed.php',                // Clear existing data
    'seed_dummy_data.php',        // Base users and orgs
    'seed_dummy1_data.php',  // More variety[cite: 2]
    'seed_dummy_reports.php',     // Standard transactions[cite: 1]
    'seed_dummy1_reports.php',    // Specific extra reports[cite: 3]
    'seed_budgetflow.php',        // Realistic BudgetFlow budgets and approvals
    'receipt_metadata.php',       // Update DB paths to match generated files
    'receipts_file.php'           // Generate the physical images
];
foreach ($seeders as $script) {
    fwrite(STDOUT, "\n--- Wait binabasa pa ang $script ---\n");
    // Use the same PHP binary that is currently running this script
    passthru("php " . __DIR__ . "/$script");
}

fwrite(STDOUT, "\n✅ All systems seeded successfully.\n");

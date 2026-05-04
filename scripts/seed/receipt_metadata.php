<?php
declare(strict_types=1);

require __DIR__ . '/../../src/core/db.php';

$pdo = db();

// Fetch transactions that don't have a path yet[cite: 1]
$stmt = $pdo->query("SELECT id, type, transaction_date FROM financial_transactions WHERE receipt_path IS NULL");
$transactions = $stmt->fetchAll();

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE financial_transactions SET receipt_path = ? WHERE id = ?");
    
    foreach ($transactions as $tx) {
        $prefix = ($tx['type'] === 'income') ? 'ACK' : 'OR';
        $datePart = date('Ymd', strtotime($tx['transaction_date']));
        $randomId = str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
        
        /** 
         * FIX: Added 'public/' to the path string.
         * This matches your working link: http://localhost/websys/public/uploads/...
         */
        $fakePath = "public/uploads/receipts/{$prefix}-{$datePart}-{$randomId}.jpg";
        
        $update->execute([$fakePath, $tx['id']]);
    }
    
    $pdo->commit();
    fwrite(STDOUT, "Successfully updated paths with 'public/' prefix for " . count($transactions) . " records.\n");
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Error seeding receipts: " . $e->getMessage() . PHP_EOL);
}
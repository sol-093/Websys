<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/core/db.php';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "ERROR: GD library is not enabled.\n");
    exit(1);
}

$pdo = db();

// Fetch transactions seeded by your dummy scripts[cite: 1, 3]
$stmt = $pdo->query("
    SELECT t.id, t.amount, t.description, t.transaction_date, t.receipt_path, o.name as org_name 
    FROM financial_transactions t
    JOIN organizations o ON t.organization_id = o.id
    WHERE t.receipt_path IS NOT NULL
");
$transactions = $stmt->fetchAll();

foreach ($transactions as $tx) {
    /**
     * FIX: We use realpath to find the base directory, 
     * then append the path directly from the database.
     * Since the database now stores "uploads/...",
     * keep generated receipt paths relative to the project root.
     */
    $basePath = realpath(__DIR__ . '/../../');
    $filePath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tx['receipt_path']);

    // Ensure the specific directory for this file exists
    $fileDir = dirname($filePath);
    if (!is_dir($fileDir)) {
        mkdir($fileDir, 0777, true);
    }
    
    $im = imagecreatetruecolor(400, 600);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    $gray = imagecolorallocate($im, 220, 220, 220);
    
    imagefill($im, 0, 0, $white);
    
    // Header for Official Receipt
    imagestring($im, 5, 110, 20, "OFFICIAL RECEIPT", $black);
    imagestring($im, 3, 40, 50, "Org: " . strtoupper($tx['org_name']), $black);
    imagestring($im, 2, 40, 70, "Date: " . $tx['transaction_date'], $black);
    imagestring($im, 2, 40, 85, "Ref: TXN-" . str_pad((string)$tx['id'], 5, '0', STR_PAD_LEFT), $black);
    
    imagerectangle($im, 30, 110, 370, 580, $black);
    
    $lines = explode("\n", wordwrap($tx['description'], 35));
    $y = 140;
    foreach ($lines as $line) {
        imagestring($im, 3, 50, $y, $line, $black);
        $y += 20;
    }
    
    imagestring($im, 5, 50, 300, "TOTAL:", $black);
    imagestring($im, 5, 220, 300, "PHP " . number_format((float)$tx['amount'], 2), $black);
    
    imagestring($im, 1, 100, 560, "DOCUMENT GENERATED FOR AUDIT PURPOSES", $gray);
    
    // Save the image to the corrected path
    imagejpeg($im, $filePath);
    imagedestroy($im);
}

fwrite(STDOUT, "Successfully generated dummy receipts in the correct directory.\n");

<?php

declare(strict_types=1);

function handleUpdateMyOrgAction(PDO $db, array $user): void
{
    requireRole(['owner']);
    $config = require __DIR__ . '/../core/config.php';
    $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    if (!$org) {
        setFlash('error', 'No organization assigned to your account.');
        redirect('?page=dashboard');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    if ($name === '') {
        setFlash('error', 'Organization name is required.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id']);
    }

    $logoPath = trim((string) ($org['logo_path'] ?? ''));
    $logoCropX = (float) ($_POST['logo_crop_x'] ?? ($org['logo_crop_x'] ?? 50));
    $logoCropY = (float) ($_POST['logo_crop_y'] ?? ($org['logo_crop_y'] ?? 50));
    $logoZoom = (float) ($_POST['logo_zoom'] ?? ($org['logo_zoom'] ?? 1));
    if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
        $uploadedLogo = handleProfileImageUpload($_FILES['logo'], (string) $config['upload_dir'], 'org_');
        if ($uploadedLogo === false) {
            redirect('?page=my_org_manage&org_id=' . (int) $org['id']);
        }

        if ($logoPath !== '' && $logoPath !== $uploadedLogo) {
            deleteStoredUpload($logoPath);
        }
        $logoPath = $uploadedLogo;
    }

    $stmt = $db->prepare('UPDATE organizations SET name = ?, description = ?, logo_path = ?, logo_crop_x = ?, logo_crop_y = ?, logo_zoom = ? WHERE id = ?');
    $stmt->execute([$name, $description, $logoPath !== '' ? $logoPath : null, $logoCropX, $logoCropY, $logoZoom, (int) $org['id']]);
    setFlash('success', 'Organization details updated.');
    redirect('?page=my_org_manage&org_id=' . (int) $org['id']);
}

function handleAddAnnouncementAction(PDO $db, array $user): void
{
    requireRole(['owner']);
    $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    if (!$org) {
        setFlash('error', 'No organization assigned.');
        redirect('?page=dashboard');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    $label = trim((string) ($_POST['label'] ?? ''));
    $durationDays = (int) ($_POST['duration_days'] ?? 30);
    $allowedDurations = [7, 14, 30, 60, 90];
    if ($title === '' || $content === '') {
        setFlash('error', 'Announcement title and content are required.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id']);
    }

    if ($label !== '' && mb_strlen($label) > 40) {
        setFlash('error', 'Announcement label must be 40 characters or less.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id']);
    }

    if (!in_array($durationDays, $allowedDurations, true)) {
        setFlash('error', 'Invalid announcement duration selected.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id']);
    }

    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $durationDays . ' days')->format('Y-m-d H:i:s');

    $stmt = $db->prepare('INSERT INTO announcements (organization_id, title, content, label, duration_days, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int) $org['id'], $title, $content, ($label !== '' ? $label : null), $durationDays, $expiresAt]);
    setFlash('success', 'Announcement posted.');
    redirect('?page=my_org_manage&org_id=' . (int) $org['id']);
}

function handleDeleteAnnouncementAction(PDO $db, array $user): void
{
    requireRole(['owner']);
    $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    $id = (int) ($_POST['announcement_id'] ?? 0);
    if ($org) {
        $stmt = $db->prepare('DELETE FROM announcements WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, (int) $org['id']]);
        setFlash('success', 'Announcement deleted.');
    }
    redirect('?page=my_org_manage&org_id=' . (int) ($org['id'] ?? 0));
}

function handlePinAnnouncementAdminAction(PDO $db, array $user): void
{
    requireRole(['admin']);
    $announcementId = (int) ($_POST['announcement_id'] ?? 0);
    $returnPage = (string) ($_POST['return_page'] ?? 'announcements');
    if (!in_array($returnPage, ['announcements', 'dashboard'], true)) {
        $returnPage = 'announcements';
    }

    if ($announcementId <= 0) {
        setFlash('error', 'Invalid announcement selected.');
        redirect('?page=' . $returnPage);
    }

    $db->beginTransaction();
    try {
        $existsStmt = $db->prepare('SELECT id FROM announcements WHERE id = ? LIMIT 1');
        $existsStmt->execute([$announcementId]);
        if (!$existsStmt->fetch()) {
            throw new RuntimeException('Announcement not found.');
        }

        $db->exec('UPDATE announcements SET is_pinned = 0, pinned_at = NULL WHERE is_pinned = 1');
        $pinStmt = $db->prepare('UPDATE announcements SET is_pinned = 1, pinned_at = CURRENT_TIMESTAMP WHERE id = ?');
        $pinStmt->execute([$announcementId]);

        $db->commit();
        auditLog((int) $user['id'], 'announcement.pin', 'announcement', $announcementId, 'Pinned announcement as important');
        setFlash('success', 'Announcement pinned as important.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlash('error', 'Unable to pin announcement.');
    }

    redirect('?page=' . $returnPage);
}

function handleUnpinAnnouncementAdminAction(PDO $db, array $user): void
{
    requireRole(['admin']);
    $announcementId = (int) ($_POST['announcement_id'] ?? 0);
    $returnPage = (string) ($_POST['return_page'] ?? 'announcements');
    if (!in_array($returnPage, ['announcements', 'dashboard'], true)) {
        $returnPage = 'announcements';
    }

    if ($announcementId <= 0) {
        setFlash('error', 'Invalid announcement selected.');
        redirect('?page=' . $returnPage);
    }

    try {
        $stmt = $db->prepare('UPDATE announcements SET is_pinned = 0, pinned_at = NULL WHERE id = ?');
        $stmt->execute([$announcementId]);
        auditLog((int) $user['id'], 'announcement.unpin', 'announcement', $announcementId, 'Unpinned important announcement');
        setFlash('success', 'Announcement unpinned.');
    } catch (Throwable $e) {
        setFlash('error', 'Unable to unpin announcement.');
    }

    redirect('?page=' . $returnPage);
}

function handleAddTransactionAction(PDO $db, array $user, array $config): void
{
    requireRole(['owner']);
    $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    if (!$org) {
        setFlash('error', 'No organization assigned.');
        redirect('?page=dashboard');
    }

    $type = (string) ($_POST['type'] ?? 'expense');
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));
    $transactionDate = (string) ($_POST['transaction_date'] ?? date('Y-m-d'));
    $receiptPath = null;

    if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $description === '') {
        setFlash('error', 'Please provide valid transaction values.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
    }

    if (!empty($_FILES['receipt']['name'])) {
        $uploadResult = validateAndStoreReceiptUpload($_FILES['receipt'], (string) $config['upload_dir']);
        if (!empty($uploadResult['error'])) {
            setFlash('error', (string) $uploadResult['error']);
            redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
        }

        $receiptPath = (string) ($uploadResult['path'] ?? '') ?: null;
    }

    $stmt = $db->prepare('INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int) $org['id'], $type, $amount, $description, $transactionDate, $receiptPath]);

    setFlash('success', 'Transaction saved.');
    redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
}

function handleUpdateTransactionAction(PDO $db, array $user): void
{
    requireRole(['owner']);
    $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    if (!$org) {
        redirect('?page=dashboard');
    }

    $txId = (int) ($_POST['tx_id'] ?? 0);
    $type = (string) ($_POST['type'] ?? 'expense');
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));
    $transactionDate = (string) ($_POST['transaction_date'] ?? date('Y-m-d'));

    if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $description === '') {
        setFlash('error', 'Invalid transaction update request.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
    }

    $existingStmt = $db->prepare('SELECT id FROM financial_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
    $existingStmt->execute([$txId, (int) $org['id']]);
    if (!$existingStmt->fetch()) {
        setFlash('error', 'Transaction not found.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
    }

    $pendingCheck = $db->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND action_type = ? AND status = ? LIMIT 1');
    $pendingCheck->execute([$txId, 'update', 'pending']);
    if ($pendingCheck->fetch()) {
        setFlash('error', 'An update request for this transaction is already pending.');
        redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
    }

    $stmt = $db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, proposed_type, proposed_amount, proposed_description, proposed_transaction_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $txId,
        (int) $org['id'],
        (int) $user['id'],
        'update',
        $type,
        $amount,
        $description,
        $transactionDate,
        'pending',
    ]);

    setFlash('success', 'Update request sent to admin for approval.');
    redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
}

function handleDeleteTransactionAction(PDO $db, array $user): void
{
    requireRole(['owner']);
    $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    $txId = (int) ($_POST['tx_id'] ?? 0);
    if ($org) {
        $existingStmt = $db->prepare('SELECT id FROM financial_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
        $existingStmt->execute([$txId, (int) $org['id']]);
        if (!$existingStmt->fetch()) {
            setFlash('error', 'Transaction not found.');
            redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
        }

        $pendingCheck = $db->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND action_type = ? AND status = ? LIMIT 1');
        $pendingCheck->execute([$txId, 'delete', 'pending']);
        if ($pendingCheck->fetch()) {
            setFlash('error', 'A delete request for this transaction is already pending.');
            redirect('?page=my_org_manage&org_id=' . (int) $org['id'] . '#tx-history');
        }

        $stmt = $db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$txId, (int) $org['id'], (int) $user['id'], 'delete', 'pending']);

        setFlash('success', 'Delete request sent to admin for approval.');
    }
    redirect('?page=my_org_manage&org_id=' . (int) ($org['id'] ?? 0) . '#tx-history');
}

function handleExportTransactionsAction(PDO $db, array $user): void
{
    requireLogin();

    if (($_GET['format'] ?? '') !== 'pdf') {
        setFlash('error', 'Unsupported export format.');
        redirect('?page=' . urlencode((string) ($_GET['page'] ?? 'dashboard')));
    }

    $orgId = (int) ($_GET['org_id'] ?? 0);
    $org = null;

    if (($user['role'] ?? '') === 'admin') {
        $orgStmt = $db->prepare('SELECT id, name FROM organizations WHERE id = ? LIMIT 1');
        $orgStmt->execute([$orgId]);
        $org = $orgStmt->fetch();
    } elseif (($user['role'] ?? '') === 'owner') {
        $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    }

    if (!$org) {
        setFlash('error', 'You do not have access to export that organization.');
        redirect('?page=' . urlencode((string) ($_GET['page'] ?? 'dashboard')) . ($orgId > 0 ? '&org_id=' . $orgId : ''));
    }

    $txTypeFilter = (string) ($_GET['tx_type'] ?? 'all');
    if (!in_array($txTypeFilter, ['all', 'income', 'expense'], true)) {
        $txTypeFilter = 'all';
    }

    $txDateSort = strtolower((string) ($_GET['tx_sort'] ?? 'desc'));
    if (!in_array($txDateSort, ['asc', 'desc'], true)) {
        $txDateSort = 'desc';
    }

    $txOrder = $txDateSort === 'asc' ? 'ASC' : 'DESC';
    $sql = 'SELECT id, type, amount, description, transaction_date, receipt_path FROM financial_transactions WHERE organization_id = ?';
    $params = [(int) $org['id']];
    if ($txTypeFilter !== 'all') {
        $sql .= ' AND type = ?';
        $params[] = $txTypeFilter;
    }
    $sql .= " ORDER BY transaction_date {$txOrder}, id {$txOrder}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $orgName = (string) ($org['name'] ?? 'organization');
    $slugSource = preg_replace('/[^A-Za-z0-9]+/', '-', $orgName) ?? '';
    $slug = strtolower(trim($slugSource, '-'));
    if ($slug === '') {
        $slug = 'organization';
    }
    $filename = $slug . '-transactions-' . date('Y-m-d') . '.pdf';

    $sanitizeText = static function (string $text): string {
        $clean = preg_replace('/[^\x20-\x7E]/', '?', $text);
        return (string) $clean;
    };

    $wrapLine = static function (string $text, int $maxChars = 108): array {
        if ($text === '') {
            return [''];
        }

        $wrapped = wordwrap($text, $maxChars, "\n", true);
        return explode("\n", $wrapped);
    };

    $formatAmount = static function (float $amount): string {
        return 'PHP ' . number_format($amount, 2);
    };

    $lines = [];
    $lines[] = 'Student Organization Management and Budget Transparency System';
    $lines[] = 'Transaction Report Export';
    $lines[] = '';
    $lines[] = 'Organization: ' . (string) $orgName;
    $lines[] = 'Generated: ' . date('Y-m-d H:i:s');
    $lines[] = 'Filter (Type): ' . ($txTypeFilter === 'all' ? 'All' : ucfirst($txTypeFilter));
    $lines[] = 'Sort (Date): ' . ($txDateSort === 'asc' ? 'Oldest first' : 'Newest first');
    $lines[] = 'Total records: ' . count($transactions);
    $lines[] = str_repeat('-', 110);

    if ($transactions === []) {
        $lines[] = 'No transactions found for the current view.';
    } else {
        foreach ($transactions as $index => $row) {
            $number = $index + 1;
            $date = (string) ($row['transaction_date'] ?? '');
            $type = strtoupper((string) ($row['type'] ?? ''));
            $amount = $formatAmount((float) ($row['amount'] ?? 0));
            $description = trim((string) ($row['description'] ?? ''));
            $receipt = trim((string) ($row['receipt_path'] ?? ''));
            $id = (int) ($row['id'] ?? 0);

            $lines[] = $number . '. Tx ID: ' . $id . ' | Date: ' . $date . ' | Type: ' . $type . ' | Amount: ' . $amount;

            foreach ($wrapLine('   Description: ' . ($description !== '' ? $description : '-')) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }

            foreach ($wrapLine('   Receipt Path: ' . ($receipt !== '' ? $receipt : '-')) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }

            $lines[] = str_repeat('-', 110);
        }
    }

    $normalizedLines = [];
    foreach ($lines as $line) {
        foreach ($wrapLine($sanitizeText($line)) as $wrappedLine) {
            $normalizedLines[] = $wrappedLine;
        }
    }

    $linesPerPage = 38;
    $pages = array_chunk($normalizedLines, $linesPerPage);
    if ($pages === []) {
        $pages = [['No data available.']];
    }

    $escapePdfText = static function (string $text): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    };

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

    $readPdfPngTemplate = static function (string $path): ?array {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $signature = fread($handle, 8);
        if ($signature !== "\x89PNG\x0D\x0A\x1A\x0A") {
            fclose($handle);
            return null;
        }

        $width = 0;
        $height = 0;
        $bits = 0;
        $colorType = 0;
        $idat = '';

        while (!feof($handle)) {
            $lengthBytes = fread($handle, 4);
            if (strlen($lengthBytes) !== 4) {
                break;
            }

            $length = (int) unpack('N', $lengthBytes)[1];
            $type = fread($handle, 4);
            if (strlen($type) !== 4) {
                break;
            }

            $data = $length > 0 ? fread($handle, $length) : '';
            fread($handle, 4);

            if ($type === 'IHDR') {
                $header = unpack('Nwidth/Nheight/Cbits/CcolorType/Ccompression/Cfilter/Cinterlace', $data);
                $width = (int) $header['width'];
                $height = (int) $header['height'];
                $bits = (int) $header['bits'];
                $colorType = (int) $header['colorType'];
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        fclose($handle);

        if ($width <= 0 || $height <= 0 || $bits !== 8 || $colorType !== 2 || $idat === '') {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'data' => $idat,
        ];
    };

    $templateImage = $readPdfPngTemplate(dirname(__DIR__, 2) . '/public/uploads/pdftemplate.png');

    $pageCount = count($pages);
    $templateObjectId = $templateImage !== null ? 4 : null;
    $pageObjectStart = $templateImage !== null ? 5 : 4;
    $contentObjectStart = $pageObjectStart + $pageCount;
    $pageRefs = [];

    if ($templateImage !== null && $templateObjectId !== null) {
        $imageStream = (string) $templateImage['data'];
        $objects[$templateObjectId] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $templateImage['width']
            . ' /Height ' . (int) $templateImage['height']
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode'
            . ' /DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns ' . (int) $templateImage['width'] . ' >>'
            . ' /Length ' . strlen($imageStream) . " >>\nstream\n" . $imageStream . "\nendstream";
    }

    for ($i = 0; $i < $pageCount; $i++) {
        $pageObjId = $pageObjectStart + $i;
        $contentObjId = $contentObjectStart + $i;
        $pageRefs[] = $pageObjId . ' 0 R';

        $stream = '';
        if ($templateObjectId !== null) {
            $stream .= "q\n595 0 0 842 0 0 cm\n/TPL Do\nQ\n";
        }
        $stream .= "BT\n/F1 9 Tf\n13 TL\n58 694 Td\n";
        foreach ($pages[$i] as $lineIndex => $lineText) {
            $escaped = $escapePdfText($lineText);
            if ($lineIndex === 0) {
                $stream .= '(' . $escaped . ") Tj\n";
            } else {
                $stream .= 'T* (' . $escaped . ") Tj\n";
            }
        }
        $stream .= "ET";

        $xObjectResource = $templateObjectId !== null ? ' /XObject << /TPL ' . $templateObjectId . ' 0 R >>' : '';
        $objects[$pageObjId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >>' . $xObjectResource . ' >> /Contents ' . $contentObjId . ' 0 R >>';
        $objects[$contentObjId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $objects[2] = '<< /Type /Pages /Count ' . $pageCount . ' /Kids [ ' . implode(' ', $pageRefs) . ' ] >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0 => 0];
    foreach ($objects as $id => $obj) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $obj . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $maxObjectId = max(array_keys($objects));
    $pdf .= 'xref' . "\n";
    $pdf .= '0 ' . ($maxObjectId + 1) . "\n";
    $pdf .= sprintf('%010d %05d f %s', 0, 65535, "\n");
    for ($i = 1; $i <= $maxObjectId; $i++) {
        $offset = (int) ($offsets[$i] ?? 0);
        $pdf .= sprintf('%010d %05d n %s', $offset, 0, "\n");
    }

    $pdf .= 'trailer' . "\n";
    $pdf .= '<< /Size ' . ($maxObjectId + 1) . ' /Root 1 0 R >>' . "\n";
    $pdf .= 'startxref' . "\n";
    $pdf .= $xrefOffset . "\n";
    $pdf .= '%%EOF';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $pdf;
    exit;
}

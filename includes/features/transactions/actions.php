<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - TRANSACTION AND CONTENT ACTIONS
 * POST handlers for owner content, announcements, and finance records
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Owner Organization Profile Updates
 * 2. Announcement Actions
 * 3. Transaction Create/Update/Delete Actions
 * 4. Transaction PDF Export
 *
 * EDIT GUIDE:
 * - Edit section 1 for owner organization profile/logo form saves.
 * - Edit section 2 for announcement add/delete/pin/unpin behavior.
 * - Edit section 3 for transaction mutation and admin-approval request behavior.
 * - Edit section 4 for PDF export output and PDF template drawing.
 * ================================================
 */

// ================================================
// 1. OWNER ORGANIZATION PROFILE UPDATES
// ================================================
function handleUpdateMyOrgAction(PDO $db, array $user): void
{
    requireRole(['owner']);
    $config = require dirname(__DIR__, 2) . '/core/config.php';
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

// ================================================
// 2. ANNOUNCEMENT ACTIONS
// ================================================
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

// ================================================
// 3. TRANSACTION CREATE/UPDATE/DELETE ACTIONS
// ================================================
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

// ================================================
// 4. TRANSACTION PDF EXPORT
// ================================================
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

    $pageWidth = 595.28;
    $pageHeight = 841.89;

    $sanitizeText = static function (string $text): string {
        $clean = preg_replace('/[^\x20-\x7E]/', ' ', $text);
        $clean = preg_replace('/\s+/', ' ', (string) $clean);
        return trim((string) $clean);
    };

    $escapePdfText = static function (string $text): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    };

    $formatPdfNumber = static function (float $number): string {
        $formatted = rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    };

    $formatAmount = static function (float $amount): string {
        return 'PHP ' . number_format($amount, 2);
    };

    $wrapText = static function (string $text, int $maxChars, int $maxLines = 2) use ($sanitizeText): array {
        $text = $sanitizeText($text);
        if ($text === '') {
            return ['-'];
        }

        $lines = explode("\n", wordwrap($text, $maxChars, "\n", true));
        $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lastIndex = $maxLines - 1;
            $lines[$lastIndex] = rtrim(substr($lines[$lastIndex], 0, max(0, $maxChars - 3))) . '...';
        }

        return $lines !== [] ? $lines : ['-'];
    };

    $truncateText = static function (string $text, int $maxChars) use ($sanitizeText): string {
        $text = $sanitizeText($text);
        if (strlen($text) <= $maxChars) {
            return $text;
        }

        return rtrim(substr($text, 0, max(0, $maxChars - 3))) . '...';
    };

    $incomeTotal = 0.0;
    $expenseTotal = 0.0;
    $tableRows = [];
    foreach ($transactions as $index => $row) {
        $amount = (float) ($row['amount'] ?? 0);
        $type = strtolower((string) ($row['type'] ?? ''));
        if ($type === 'income') {
            $incomeTotal += $amount;
        } elseif ($type === 'expense') {
            $expenseTotal += $amount;
        }

        $descriptionLines = $wrapText((string) ($row['description'] ?? ''), 48, 2);
        $receiptPath = trim((string) ($row['receipt_path'] ?? ''));
        $receiptName = $receiptPath !== '' ? basename(str_replace('\\', '/', $receiptPath)) : '-';
        $lineCount = max(1, count($descriptionLines));

        $tableRows[] = [
            'number' => $index + 1,
            'id' => (int) ($row['id'] ?? 0),
            'date' => $truncateText((string) ($row['transaction_date'] ?? ''), 10),
            'type' => $type === 'income' ? 'Income' : 'Expense',
            'amount' => $formatAmount($amount),
            'description_lines' => $descriptionLines,
            'receipt' => $truncateText($receiptName, 28),
            'height' => 38 + ($lineCount * 10) + ($receiptName !== '-' ? 10 : 0),
        ];
    }

    $netTotal = $incomeTotal - $expenseTotal;
    $reportGeneratedAt = date('M d, Y h:i A');
    $typeLabel = $txTypeFilter === 'all' ? 'All transactions' : ucfirst($txTypeFilter) . ' only';
    $sortLabel = $txDateSort === 'asc' ? 'Oldest first' : 'Newest first';

    $tableStartY = 272.0;
    $tableHeaderHeight = 24.0;
    $tableBottomY = 710.0;
    $contentPages = [[]];
    $currentPage = 0;
    $cursorY = $tableStartY + $tableHeaderHeight;

    foreach ($tableRows as $row) {
        if ($cursorY + (float) $row['height'] > $tableBottomY && $contentPages[$currentPage] !== []) {
            $contentPages[] = [];
            $currentPage++;
            $cursorY = $tableStartY + $tableHeaderHeight;
        }

        $contentPages[$currentPage][] = $row;
        $cursorY += (float) $row['height'];
    }

    if ($contentPages === [] || ($contentPages[0] === [] && $tableRows !== [])) {
        $contentPages = [[]];
    }

    $drawText = static function (
        float $x,
        float $topY,
        string $font,
        float $size,
        string $text,
        array $rgb = [0.10, 0.15, 0.13]
    ) use ($escapePdfText, $formatPdfNumber, $pageHeight): string {
        return 'BT ' . $formatPdfNumber((float) $rgb[0]) . ' ' . $formatPdfNumber((float) $rgb[1]) . ' ' . $formatPdfNumber((float) $rgb[2]) . ' rg '
            . '/' . $font . ' ' . $formatPdfNumber($size) . ' Tf '
            . $formatPdfNumber($x) . ' ' . $formatPdfNumber($pageHeight - $topY) . ' Td '
            . '(' . $escapePdfText($text) . ") Tj ET\n";
    };

    $drawRightText = static function (
        float $rightX,
        float $topY,
        string $font,
        float $size,
        string $text,
        array $rgb = [0.10, 0.15, 0.13]
    ) use ($drawText): string {
        $estimatedWidth = strlen($text) * $size * 0.52;
        return $drawText($rightX - $estimatedWidth, $topY, $font, $size, $text, $rgb);
    };

    $drawRect = static function (
        float $x,
        float $topY,
        float $width,
        float $height,
        ?array $fillRgb = null,
        ?array $strokeRgb = null,
        ?string $graphicsState = null
    ) use ($formatPdfNumber, $pageHeight): string {
        $y = $pageHeight - $topY - $height;
        $stream = "q\n";
        if ($graphicsState !== null) {
            $stream .= '/' . $graphicsState . " gs\n";
        }
        if ($fillRgb !== null) {
            $stream .= $formatPdfNumber((float) $fillRgb[0]) . ' ' . $formatPdfNumber((float) $fillRgb[1]) . ' ' . $formatPdfNumber((float) $fillRgb[2]) . " rg\n";
        }
        if ($strokeRgb !== null) {
            $stream .= $formatPdfNumber((float) $strokeRgb[0]) . ' ' . $formatPdfNumber((float) $strokeRgb[1]) . ' ' . $formatPdfNumber((float) $strokeRgb[2]) . " RG\n";
        }
        $stream .= $formatPdfNumber($x) . ' ' . $formatPdfNumber($y) . ' ' . $formatPdfNumber($width) . ' ' . $formatPdfNumber($height) . ' re ';
        $stream .= $fillRgb !== null && $strokeRgb !== null ? "B\n" : ($fillRgb !== null ? "f\n" : "S\n");
        $stream .= "Q\n";
        return $stream;
    };

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

        if ($width <= 0 || $height <= 0 || $bits !== 8 || $idat === '') {
            return null;
        }

        if ($colorType !== 2) {
            if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) {
                return null;
            }

            $source = @imagecreatefrompng($path);
            if ($source === false) {
                return null;
            }

            $canvas = imagecreatetruecolor($width, $height);
            if ($canvas === false) {
                imagedestroy($source);
                return null;
            }

            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white !== false ? $white : 0);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

            ob_start();
            imagejpeg($canvas, null, 94);
            $jpegData = (string) ob_get_clean();

            imagedestroy($canvas);
            imagedestroy($source);

            if ($jpegData === '') {
                return null;
            }

            return [
                'width' => $width,
                'height' => $height,
                'data' => $jpegData,
                'filter' => 'DCTDecode',
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'data' => $idat,
            'filter' => 'FlateDecode',
        ];
    };

    $templateImage = $readPdfPngTemplate(dirname(__DIR__, 3) . '/uploads/pdftemplate.png');

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
    $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>';
    $objects[6] = '<< /Type /ExtGState /ca 0.88 /CA 0.88 >>';
    $objects[7] = '<< /Type /ExtGState /ca 0.72 /CA 0.72 >>';

    $pageCount = count($contentPages);
    $templateObjectId = $templateImage !== null ? 8 : null;
    $pageObjectStart = $templateImage !== null ? 9 : 8;
    $contentObjectStart = $pageObjectStart + $pageCount;
    $pageRefs = [];

    if ($templateImage !== null && $templateObjectId !== null) {
        $imageStream = (string) $templateImage['data'];
        $imageFilter = (string) ($templateImage['filter'] ?? 'FlateDecode');
        $decodeParms = $imageFilter === 'FlateDecode'
            ? ' /DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns ' . (int) $templateImage['width'] . ' >>'
            : '';
        $objects[$templateObjectId] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $templateImage['width']
            . ' /Height ' . (int) $templateImage['height']
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /' . $imageFilter
            . $decodeParms
            . ' /Length ' . strlen($imageStream) . " >>\nstream\n" . $imageStream . "\nendstream";
    }

    for ($i = 0; $i < $pageCount; $i++) {
        $pageObjId = $pageObjectStart + $i;
        $contentObjId = $contentObjectStart + $i;
        $pageRefs[] = $pageObjId . ' 0 R';

        $stream = '';
        if ($templateObjectId !== null) {
            $stream .= "q\n" . $formatPdfNumber($pageWidth) . " 0 0 " . $formatPdfNumber($pageHeight) . " 0 0 cm\n/TPL Do\nQ\n";
        }

        $stream .= $drawText(58, 94, 'F2', 18, 'Transaction Report', [0.06, 0.20, 0.15]);
        $stream .= $drawText(58, 114, 'F1', 8.5, 'Student Organization Management and Budget Transparency System', [0.29, 0.36, 0.33]);
        $stream .= $drawRightText(537, 100, 'F1', 8.2, 'Generated ' . $reportGeneratedAt, [0.36, 0.41, 0.39]);

        $stream .= $drawRect(58, 140, 479, 74, [1, 1, 1], [0.78, 0.86, 0.82], 'GSPanel');
        $stream .= $drawText(76, 163, 'F2', 10.5, $truncateText($orgName, 58), [0.07, 0.22, 0.16]);
        $stream .= $drawText(76, 184, 'F1', 8.5, 'Type: ' . $typeLabel, [0.28, 0.34, 0.32]);
        $stream .= $drawText(252, 184, 'F1', 8.5, 'Date sort: ' . $sortLabel, [0.28, 0.34, 0.32]);
        $stream .= $drawText(410, 184, 'F1', 8.5, 'Records: ' . count($transactions), [0.28, 0.34, 0.32]);

        $summaryY = 222.0;
        $summaryWidth = 151.0;
        $summaryCards = [
            ['label' => 'Total Income', 'value' => $formatAmount($incomeTotal), 'x' => 58.0, 'fill' => [0.94, 0.99, 0.96], 'stroke' => [0.65, 0.86, 0.72], 'valueColor' => [0.05, 0.43, 0.22]],
            ['label' => 'Total Expense', 'value' => $formatAmount($expenseTotal), 'x' => 222.0, 'fill' => [1.00, 0.96, 0.95], 'stroke' => [0.90, 0.70, 0.67], 'valueColor' => [0.61, 0.16, 0.12]],
            ['label' => 'Net Balance', 'value' => $formatAmount($netTotal), 'x' => 386.0, 'fill' => [0.95, 0.98, 1.00], 'stroke' => [0.67, 0.80, 0.91], 'valueColor' => [0.08, 0.27, 0.45]],
        ];
        foreach ($summaryCards as $card) {
            $stream .= $drawRect((float) $card['x'], $summaryY, $summaryWidth, 38, $card['fill'], $card['stroke'], 'GSPanel');
            $stream .= $drawText((float) $card['x'] + 12, $summaryY + 15, 'F1', 7.5, (string) $card['label'], [0.40, 0.46, 0.43]);
            $stream .= $drawText((float) $card['x'] + 12, $summaryY + 30, 'F2', 10, (string) $card['value'], $card['valueColor']);
        }

        $stream .= $drawRect(58, $tableStartY, 479, $tableHeaderHeight, [0.08, 0.27, 0.19], null);
        $stream .= $drawText(70, $tableStartY + 16, 'F2', 7.5, 'DATE', [1, 1, 1]);
        $stream .= $drawText(133, $tableStartY + 16, 'F2', 7.5, 'TYPE', [1, 1, 1]);
        $stream .= $drawText(193, $tableStartY + 16, 'F2', 7.5, 'DESCRIPTION / RECEIPT', [1, 1, 1]);
        $stream .= $drawRightText(521, $tableStartY + 16, 'F2', 7.5, 'AMOUNT', [1, 1, 1]);

        $rowY = $tableStartY + $tableHeaderHeight;
        if ($transactions === []) {
            $stream .= $drawRect(58, $rowY, 479, 58, [1, 1, 1], [0.86, 0.90, 0.88]);
            $stream .= $drawText(78, $rowY + 33, 'F3', 9.5, 'No transactions found for the current filters.', [0.39, 0.45, 0.42]);
        }

        foreach ($contentPages[$i] as $rowIndex => $row) {
            $fill = $rowIndex % 2 === 0 ? [1, 1, 1] : [0.97, 0.99, 0.98];
            $stream .= $drawRect(58, $rowY, 479, (float) $row['height'], $fill, [0.88, 0.92, 0.90], 'GSTable');
            $stream .= $drawText(70, $rowY + 16, 'F1', 8, (string) $row['date'], [0.18, 0.24, 0.22]);
            $stream .= $drawText(133, $rowY + 16, 'F2', 8, (string) $row['type'], $row['type'] === 'Income' ? [0.06, 0.43, 0.22] : [0.60, 0.16, 0.12]);
            $stream .= $drawText(193, $rowY + 15, 'F1', 8, '#' . $row['number'] . '  Tx ID ' . $row['id'], [0.42, 0.47, 0.44]);
            $descriptionY = $rowY + 27;
            foreach ($row['description_lines'] as $line) {
                $stream .= $drawText(193, $descriptionY, 'F1', 8.3, (string) $line, [0.12, 0.17, 0.15]);
                $descriptionY += 11;
            }
            $stream .= $drawText(193, $descriptionY + 2, 'F3', 7.2, 'Receipt: ' . (string) $row['receipt'], [0.45, 0.50, 0.48]);
            $stream .= $drawRightText(521, $rowY + 20, 'F2', 8.5, (string) $row['amount'], [0.10, 0.15, 0.13]);
            $rowY += (float) $row['height'];
        }

        $stream .= $drawRect(58, 726, 479, 0.5, null, [0.72, 0.79, 0.76]);
        $stream .= $drawText(58, 824, 'F1', 7.3, 'Report reflects transactions available to your account at export time.', [0.34, 0.39, 0.37]);
        $stream .= $drawRightText(537, 824, 'F1', 7.3, 'Page ' . ($i + 1) . ' of ' . $pageCount, [0.34, 0.39, 0.37]);

        $xObjectResource = $templateObjectId !== null ? ' /XObject << /TPL ' . $templateObjectId . ' 0 R >>' : '';
        $objects[$pageObjId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $formatPdfNumber($pageWidth) . ' ' . $formatPdfNumber($pageHeight) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> /ExtGState << /GSPanel 6 0 R /GSTable 7 0 R >>' . $xObjectResource . ' >> /Contents ' . $contentObjId . ' 0 R >>';
        $objects[$contentObjId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $objects[2] = '<< /Type /Pages /Count ' . $pageCount . ' /Kids [ ' . implode(' ', $pageRefs) . ' ] >>';

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

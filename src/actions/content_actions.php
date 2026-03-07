<?php

declare(strict_types=1);

function handleUpdateMyOrgAction(PDO $db, array $user): void
{
    requireRole(['owner']);
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
        redirect('?page=my_org');
    }

    $stmt = $db->prepare('UPDATE organizations SET name = ?, description = ? WHERE id = ?');
    $stmt->execute([$name, $description, (int) $org['id']]);
    setFlash('success', 'Organization details updated.');
    redirect('?page=my_org&org_id=' . (int) $org['id']);
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
    if ($title === '' || $content === '') {
        setFlash('error', 'Announcement title and content are required.');
        redirect('?page=my_org');
    }

    $stmt = $db->prepare('INSERT INTO announcements (organization_id, title, content) VALUES (?, ?, ?)');
    $stmt->execute([(int) $org['id'], $title, $content]);
    setFlash('success', 'Announcement posted.');
    redirect('?page=my_org&org_id=' . (int) $org['id']);
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
    redirect('?page=my_org&org_id=' . (int) ($org['id'] ?? 0));
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
        redirect('?page=my_org');
    }

    if (!empty($_FILES['receipt']['name'])) {
        $uploadResult = validateAndStoreReceiptUpload($_FILES['receipt'], (string) $config['upload_dir']);
        if (!empty($uploadResult['error'])) {
            setFlash('error', (string) $uploadResult['error']);
            redirect('?page=my_org&org_id=' . (int) $org['id']);
        }

        $receiptPath = (string) ($uploadResult['path'] ?? '') ?: null;
    }

    $stmt = $db->prepare('INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int) $org['id'], $type, $amount, $description, $transactionDate, $receiptPath]);

    setFlash('success', 'Transaction saved.');
    redirect('?page=my_org&org_id=' . (int) $org['id']);
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
        redirect('?page=my_org&org_id=' . (int) $org['id']);
    }

    $existingStmt = $db->prepare('SELECT id FROM financial_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
    $existingStmt->execute([$txId, (int) $org['id']]);
    if (!$existingStmt->fetch()) {
        setFlash('error', 'Transaction not found.');
        redirect('?page=my_org&org_id=' . (int) $org['id']);
    }

    $pendingCheck = $db->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND action_type = ? AND status = ? LIMIT 1');
    $pendingCheck->execute([$txId, 'update', 'pending']);
    if ($pendingCheck->fetch()) {
        setFlash('error', 'An update request for this transaction is already pending.');
        redirect('?page=my_org&org_id=' . (int) $org['id']);
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
    redirect('?page=my_org&org_id=' . (int) $org['id']);
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
            redirect('?page=my_org&org_id=' . (int) $org['id']);
        }

        $pendingCheck = $db->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND action_type = ? AND status = ? LIMIT 1');
        $pendingCheck->execute([$txId, 'delete', 'pending']);
        if ($pendingCheck->fetch()) {
            setFlash('error', 'A delete request for this transaction is already pending.');
            redirect('?page=my_org&org_id=' . (int) $org['id']);
        }

        $stmt = $db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$txId, (int) $org['id'], (int) $user['id'], 'delete', 'pending']);

        setFlash('success', 'Delete request sent to admin for approval.');
    }
    redirect('?page=my_org&org_id=' . (int) ($org['id'] ?? 0));
}

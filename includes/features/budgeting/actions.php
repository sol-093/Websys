<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - BUDGETING ACTIONS
 * ================================================
 *
 * SECTION MAP:
 * 1. Owner Budget Actions
 *
 * WORK GUIDE:
 * - Keep budgeting business rules in includes/lib/budgeting.php.
 * - Keep redirects and flash messages in this action layer.
 * ================================================
 */

function handleCreateBudgetAction(PDO $db, array $user): void
{
    requireRole(['owner']);

    $orgId = (int) ($_POST['org_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    if (!$org) {
        setFlash('error', 'Selected organization is not assigned to your account.');
        redirect('?page=my_org');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $periodStart = (string) ($_POST['period_start'] ?? '');
    $periodEnd = (string) ($_POST['period_end'] ?? '');
    $totalAmount = (float) ($_POST['total_amount'] ?? 0);

    try {
        $budgetId = createBudget($db, (int) $org['id'], (int) $user['id'], $title, $periodStart, $periodEnd, $totalAmount, 'draft');
        auditLog((int) $user['id'], 'budget.create', 'budget', $budgetId, 'Created draft budget: ' . $title);
        setFlash('success', 'Budget draft created.');
        redirect('?page=my_org_budget&org_id=' . (int) $org['id'] . '&budget_id=' . $budgetId);
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to create budget.');
        redirect('?page=my_org_budget&org_id=' . (int) $org['id']);
    }
}

function handleAddBudgetLineItemAction(PDO $db, array $user): void
{
    requireRole(['owner']);

    $orgId = (int) ($_POST['org_id'] ?? 0);
    $budgetId = (int) ($_POST['budget_id'] ?? 0);
    $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    if (!$org || !getBudgetById($db, $budgetId, (int) ($org['id'] ?? 0))) {
        setFlash('error', 'Budget was not found for your organization.');
        redirect('?page=my_org_budget&org_id=' . $orgId);
    }

    $categoryName = trim((string) ($_POST['category_name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $allocatedAmount = (float) ($_POST['allocated_amount'] ?? 0);

    try {
        $lineId = createBudgetLineItem($db, $budgetId, $categoryName, $description, $allocatedAmount);
        auditLog((int) $user['id'], 'budget.line_create', 'budget_line_item', $lineId, 'Added budget line item: ' . $categoryName);
        setFlash('success', 'Budget line item added.');
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to add budget line item.');
    }

    redirect('?page=my_org_budget&org_id=' . (int) $org['id'] . '&budget_id=' . $budgetId . '#budget-lines');
}

function handleUpdateBudgetStatusAction(PDO $db, array $user): void
{
    requireRole(['owner']);

    $orgId = (int) ($_POST['org_id'] ?? 0);
    $budgetId = (int) ($_POST['budget_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    if (!$org || !getBudgetById($db, $budgetId, (int) ($org['id'] ?? 0))) {
        setFlash('error', 'Budget was not found for your organization.');
        redirect('?page=my_org_budget&org_id=' . $orgId);
    }

    try {
        updateBudgetStatus($db, $budgetId, $status);
        auditLog((int) $user['id'], 'budget.status_update', 'budget', $budgetId, 'Budget status changed to ' . $status);
        setFlash('success', 'Budget status updated.');
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to update budget status.');
    }

    redirect('?page=my_org_budget&org_id=' . (int) $org['id'] . '&budget_id=' . $budgetId);
}

function handleExportOwnerBudgetAction(PDO $db, array $user): void
{
    requireRole(['owner']);

    $orgId = (int) ($_GET['org_id'] ?? 0);
    $budgetId = (int) ($_GET['budget_id'] ?? 0);
    $format = (string) ($_GET['format'] ?? 'xls');
    if (!in_array($format, ['xls', 'csv'], true)) {
        setFlash('error', 'Unsupported budget export format.');
        redirect('?page=my_org_budget&org_id=' . $orgId);
    }

    $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    if (!$org) {
        setFlash('error', 'Selected organization is not assigned to your account.');
        redirect('?page=my_org');
    }

    $budget = getBudgetById($db, $budgetId, (int) $org['id']);
    if (!$budget) {
        setFlash('error', 'Budget was not found for your organization.');
        redirect('?page=my_org_budget&org_id=' . (int) $org['id']);
    }

    $budgetLines = getBudgetLineItems($db, (int) $budget['id']);
    $allocatedTotal = 0.0;
    $spentTotal = 0.0;
    $pendingTotal = 0.0;
    $remainingTotal = 0.0;
    $criticalCount = 0;
    $watchCount = 0;
    $rows = [];

    foreach ($budgetLines as $line) {
        $allocated = (float) ($line['allocated_amount'] ?? 0);
        $spent = (float) ($line['spent_amount'] ?? 0);
        $pending = (float) ($line['pending_amount'] ?? 0);
        $remaining = (float) ($line['remaining_amount'] ?? 0);
        $usagePercent = $allocated > 0 ? min(100, (($spent + $pending) / $allocated) * 100) : 0;
        $usageState = $remaining <= 0 ? 'Exhausted' : ($usagePercent >= 90 ? 'Critical' : ($usagePercent >= 75 ? 'Watch' : 'Healthy'));

        if ($usageState === 'Critical' || $usageState === 'Exhausted') {
            $criticalCount++;
        } elseif ($usageState === 'Watch') {
            $watchCount++;
        }

        $allocatedTotal += $allocated;
        $spentTotal += $spent;
        $pendingTotal += $pending;
        $remainingTotal += $remaining;
        $rows[] = [
            (string) ($line['category_name'] ?? ''),
            number_format($allocated, 2, '.', ''),
            number_format($spent, 2, '.', ''),
            number_format($pending, 2, '.', ''),
            number_format($remaining, 2, '.', ''),
            number_format($usagePercent, 2, '.', ''),
            $usageState,
            (string) ($line['description'] ?? ''),
        ];
    }

    $filters = [
        'Organization' => (string) ($org['name'] ?? 'Organization'),
        'Budget' => (string) ($budget['title'] ?? 'Budget'),
        'Status' => ucfirst((string) ($budget['status'] ?? '')),
        'Period' => date('F d, Y', strtotime((string) $budget['period_start'])) . ' to ' . date('F d, Y', strtotime((string) $budget['period_end'])),
    ];
    $summary = [
        'Budget Total (PHP)' => number_format((float) ($budget['total_amount'] ?? 0), 2, '.', ''),
        'Allocated (PHP)' => number_format($allocatedTotal, 2, '.', ''),
        'Spent (PHP)' => number_format($spentTotal, 2, '.', ''),
        'Pending Amount (PHP)' => number_format($pendingTotal, 2, '.', ''),
        'Remaining (PHP)' => number_format($remainingTotal, 2, '.', ''),
        'Line Items' => count($budgetLines),
        'Critical Lines' => $criticalCount,
        'Watch Lines' => $watchCount,
    ];
    $headers = [
        'Category',
        'Allocated (PHP)',
        'Spent (PHP)',
        'Pending Amount (PHP)',
        'Remaining (PHP)',
        'Usage Percent',
        'Status',
        'Description',
    ];

    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($budget['title'] ?? 'budget'))) ?: 'budget';
    if ($format === 'xls') {
        sendAdminExcelReport($slug . '-budget-lines-' . date('Y-m-d') . '.xls', 'INVOLVE BudgetFlow - Owner Budget Line Report', $filters, $summary, $headers, $rows);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $slug . '-budget-lines-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    writeAdminCsvReportHeader($out, 'INVOLVE BudgetFlow - Owner Budget Line Report', $filters, $summary);
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function handleSubmitExpenseRequestAction(PDO $db, array $user, array $config): void
{
    requireRole(['owner']);

    $orgId = (int) ($_POST['org_id'] ?? 0);
    $lineItemId = (int) ($_POST['budget_line_item_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));
    $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    if (!$org) {
        setFlash('error', 'Selected organization is not assigned to your account.');
        redirect('?page=my_org');
    }

    $receiptPath = null;
    if (!empty($_FILES['receipt']['name'])) {
        $uploadResult = validateAndStoreReceiptUpload($_FILES['receipt'], (string) $config['upload_dir']);
        if (!empty($uploadResult['error'])) {
            setFlash('error', (string) $uploadResult['error']);
            redirect('?page=my_org_finance&org_id=' . (int) $org['id'] . '#expense-requests');
        }

        $receiptPath = (string) ($uploadResult['path'] ?? '') ?: null;
    }

    try {
        createExpenseRequest($db, (int) $org['id'], $lineItemId, (int) $user['id'], $amount, $description, $receiptPath);
        setFlash('success', 'Expense request submitted for admin approval.');
    } catch (Throwable $e) {
        if ($receiptPath !== null) {
            deleteStoredUpload($receiptPath);
        }
        setFlash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to submit expense request.');
    }

    redirect('?page=my_org_finance&org_id=' . (int) $org['id'] . '#expense-requests');
}

function handleProcessExpenseRequestAction(PDO $db, array $user): void
{
    requireRole(['admin']);

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));

    if (!in_array($decision, ['approve', 'reject'], true)) {
        setFlash('error', 'Invalid expense request decision.');
        redirect('?page=admin_expense_requests');
    }

    try {
        if ($decision === 'approve') {
            approveExpenseRequest($db, $requestId, (int) $user['id'], $adminNote);
            setFlash('success', 'Expense request approved and linked transaction created.');
        } else {
            rejectExpenseRequest($db, $requestId, (int) $user['id'], $adminNote);
            setFlash('success', 'Expense request rejected.');
        }
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to process expense request.');
    }

    redirect('?page=admin_expense_requests');
}

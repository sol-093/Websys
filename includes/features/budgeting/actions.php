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
            approveExpenseRequest($db, $requestId, (int) $user['id']);
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

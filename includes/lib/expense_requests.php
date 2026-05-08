<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - EXPENSE REQUEST HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. Request Fetchers
 * 2. Request Validation
 * 3. Request Mutations
 * 4. Approval Workflow
 *
 * WORK GUIDE:
 * - Keep form handling, redirects, and page output outside this file.
 * - Approval creates the real expense transaction and links both records.
 * ================================================
 */

function getExpenseRequests(PDO $db, array $filters = []): array
{
    $sql = "SELECT er.*, o.name AS organization_name, b.title AS budget_title, bli.category_name AS line_item_name,
                   requester.name AS requested_by_name, reviewer.name AS reviewed_by_name
            FROM expense_requests er
            JOIN organizations o ON o.id = er.organization_id
            JOIN budgets b ON b.id = er.budget_id
            JOIN budget_line_items bli ON bli.id = er.budget_line_item_id
            LEFT JOIN users requester ON requester.id = er.requested_by
            LEFT JOIN users reviewer ON reviewer.id = er.reviewed_by
            WHERE 1 = 1";
    $params = [];

    if (!empty($filters['organization_id'])) {
        $sql .= ' AND er.organization_id = ?';
        $params[] = (int) $filters['organization_id'];
    }

    if (!empty($filters['budget_id'])) {
        $sql .= ' AND er.budget_id = ?';
        $params[] = (int) $filters['budget_id'];
    }

    if (!empty($filters['requested_by'])) {
        $sql .= ' AND er.requested_by = ?';
        $params[] = (int) $filters['requested_by'];
    }

    if (!empty($filters['status']) && in_array((string) $filters['status'], ['pending', 'approved', 'rejected'], true)) {
        $sql .= ' AND er.status = ?';
        $params[] = (string) $filters['status'];
    }

    $limit = isset($filters['limit']) ? max(1, min(200, (int) $filters['limit'])) : 100;
    $sql .= ' ORDER BY er.created_at DESC, er.id DESC LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function getExpenseRequestById(PDO $db, int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    $stmt = $db->prepare(
        "SELECT er.*, o.name AS organization_name, b.title AS budget_title, bli.category_name AS line_item_name,
                requester.name AS requested_by_name, reviewer.name AS reviewed_by_name
         FROM expense_requests er
         JOIN organizations o ON o.id = er.organization_id
         JOIN budgets b ON b.id = er.budget_id
         JOIN budget_line_items bli ON bli.id = er.budget_line_item_id
         LEFT JOIN users requester ON requester.id = er.requested_by
         LEFT JOIN users reviewer ON reviewer.id = er.reviewed_by
         WHERE er.id = ?
         LIMIT 1"
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    return $request ?: null;
}

function validateExpenseRequestAgainstAllocation(PDO $db, int $organizationId, int $budgetLineItemId, float $amount, ?int $excludeRequestId = null, bool $enforceBudgetWindow = true): array
{
    $amount = round($amount, 2);
    if ($organizationId <= 0 || $budgetLineItemId <= 0 || $amount <= 0) {
        return ['valid' => false, 'error' => 'Please provide a valid expense request amount.'];
    }

    $line = getBudgetLineItemById($db, $budgetLineItemId);
    if (!$line) {
        return ['valid' => false, 'error' => 'Budget line item was not found.'];
    }

    $budget = getBudgetById($db, (int) $line['budget_id'], $organizationId);
    if (!$budget) {
        return ['valid' => false, 'error' => 'Budget line item does not belong to this organization.'];
    }

    $budgetStatus = (string) ($budget['status'] ?? '');
    if ($enforceBudgetWindow && $budgetStatus !== 'active') {
        return ['valid' => false, 'error' => 'Expense requests can only be submitted against an active budget.'];
    }

    if (!$enforceBudgetWindow && !in_array($budgetStatus, ['active', 'closed'], true)) {
        return ['valid' => false, 'error' => 'Expense request cannot be approved against a draft budget.'];
    }

    $today = date('Y-m-d');
    if ($enforceBudgetWindow && ((string) $budget['period_start'] > $today || (string) $budget['period_end'] < $today)) {
        return ['valid' => false, 'error' => 'The selected budget is outside its active date range.'];
    }

    $remainingAmount = getBudgetLineRemainingAmount($db, $line, true, $excludeRequestId);
    if ($amount > $remainingAmount) {
        return [
            'valid' => false,
            'error' => 'Expense request exceeds the remaining budget allocation.',
            'remaining_amount' => $remainingAmount,
        ];
    }

    return [
        'valid' => true,
        'budget' => $budget,
        'line_item' => $line,
        'remaining_amount' => $remainingAmount,
    ];
}

function createExpenseRequest(PDO $db, int $organizationId, int $budgetLineItemId, int $requestedBy, float $amount, string $description, ?string $receiptPath = null): int
{
    $description = trim($description);
    $receiptPath = trim((string) $receiptPath);
    $validation = validateExpenseRequestAgainstAllocation($db, $organizationId, $budgetLineItemId, $amount);

    if (!$validation['valid']) {
        throw new RuntimeException((string) ($validation['error'] ?? 'Invalid expense request.'));
    }

    if ($requestedBy <= 0 || $description === '') {
        throw new InvalidArgumentException('Expense request description and requester are required.');
    }

    $budget = $validation['budget'];
    $stmt = $db->prepare(
        'INSERT INTO expense_requests (organization_id, budget_id, budget_line_item_id, requested_by, amount, description, receipt_path, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $organizationId,
        (int) $budget['id'],
        $budgetLineItemId,
        $requestedBy,
        round($amount, 2),
        $description,
        $receiptPath !== '' ? $receiptPath : null,
        'pending',
    ]);

    $requestId = (int) $db->lastInsertId();
    auditLog($requestedBy, 'budget.expense_request_submit', 'expense_request', $requestId, 'Submitted expense request for approval');
    queueBudgetExpenseRequestSubmittedNotifications($db, $requestId);

    return $requestId;
}

function approveExpenseRequest(PDO $db, int $requestId, int $reviewedBy, string $adminNote): int
{
    $adminNote = trim($adminNote);
    if ($requestId <= 0 || $reviewedBy <= 0) {
        throw new InvalidArgumentException('Invalid approval request.');
    }

    if ($adminNote === '') {
        throw new InvalidArgumentException('An approval note is required.');
    }

    $db->beginTransaction();
    try {
        $request = lockExpenseRequestForDecision($db, $requestId);
        if (!$request) {
            throw new RuntimeException('Expense request not found.');
        }

        if ((string) ($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Only pending expense requests can be approved.');
        }

        $validation = validateExpenseRequestAgainstAllocation($db, (int) $request['organization_id'], (int) $request['budget_line_item_id'], (float) $request['amount'], $requestId, false);
        if (!$validation['valid']) {
            throw new RuntimeException((string) ($validation['error'] ?? 'Expense request is no longer valid.'));
        }

        $transactionStmt = $db->prepare(
            'INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path, expense_request_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $transactionStmt->execute([
            (int) $request['organization_id'],
            'expense',
            round((float) $request['amount'], 2),
            (string) $request['description'],
            date('Y-m-d'),
            $request['receipt_path'] !== null && $request['receipt_path'] !== '' ? (string) $request['receipt_path'] : null,
            $requestId,
        ]);
        $transactionId = (int) $db->lastInsertId();

        $requestStmt = $db->prepare("UPDATE expense_requests SET status = 'approved', admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, transaction_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $requestStmt->execute([$adminNote, $reviewedBy, $transactionId, $requestId]);

        $lineStmt = $db->prepare('UPDATE budget_line_items SET spent_amount = spent_amount + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $lineStmt->execute([round((float) $request['amount'], 2), (int) $request['budget_line_item_id']]);

        $db->commit();
        auditLog($reviewedBy, 'budget.expense_request_approve', 'expense_request', $requestId, 'Approved expense request and created transaction #' . $transactionId . ': ' . $adminNote);
        queueBudgetExpenseRequestDecisionNotification($db, $requestId, 'budget_expense_request_approved', $adminNote);

        return $transactionId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function rejectExpenseRequest(PDO $db, int $requestId, int $reviewedBy, string $adminNote): void
{
    $adminNote = trim($adminNote);
    if ($requestId <= 0 || $reviewedBy <= 0 || $adminNote === '') {
        throw new InvalidArgumentException('A rejection note is required.');
    }

    $db->beginTransaction();
    try {
        $request = lockExpenseRequestForDecision($db, $requestId);
        if (!$request) {
            throw new RuntimeException('Expense request not found.');
        }

        if ((string) ($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Only pending expense requests can be rejected.');
        }

        $stmt = $db->prepare("UPDATE expense_requests SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$adminNote, $reviewedBy, $requestId]);

        $db->commit();
        auditLog($reviewedBy, 'budget.expense_request_reject', 'expense_request', $requestId, 'Rejected expense request: ' . $adminNote);
        queueBudgetExpenseRequestDecisionNotification($db, $requestId, 'budget_expense_request_rejected', $adminNote);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function lockExpenseRequestForDecision(PDO $db, int $requestId): ?array
{
    $driver = (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = 'SELECT * FROM expense_requests WHERE id = ? LIMIT 1';
    if ($driver === 'mysql') {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    return $request ?: null;
}

function renderExpenseRequestTimeline(array $request): void
{
    $status = strtolower((string) ($request['status'] ?? 'pending'));
    $createdAt = trim((string) ($request['created_at'] ?? ''));
    $reviewedAt = trim((string) ($request['reviewed_at'] ?? ''));
    $requesterName = trim((string) ($request['requested_by_name'] ?? 'Owner'));
    $reviewerName = trim((string) ($request['reviewed_by_name'] ?? 'Admin'));
    $adminNote = trim((string) ($request['admin_note'] ?? ''));
    $decisionLabel = match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Awaiting decision',
    };
    $decisionIcon = match ($status) {
        'approved' => 'approved',
        'rejected' => 'rejected',
        default => 'pending',
    };
    $decisionClass = 'expense-timeline-step-' . preg_replace('/[^a-z]/', '', $status);
    ?>
    <ol class="expense-request-timeline" aria-label="Expense request timeline">
        <li class="expense-timeline-step expense-timeline-step-submitted">
            <span class="expense-timeline-dot"><?= uiIcon('requests', 'ui-icon ui-icon-sm') ?></span>
            <span class="expense-timeline-content">
                <span class="expense-timeline-title">Submitted</span>
                <span class="expense-timeline-meta">
                    <?= e($requesterName) ?>
                    <?= $createdAt !== '' ? ' · ' . e(date('M d, Y g:i A', strtotime($createdAt))) : '' ?>
                </span>
            </span>
        </li>
        <li class="expense-timeline-step <?= e($decisionClass) ?>">
            <span class="expense-timeline-dot"><?= uiIcon($decisionIcon, 'ui-icon ui-icon-sm') ?></span>
            <span class="expense-timeline-content">
                <span class="expense-timeline-title"><?= e($decisionLabel) ?></span>
                <span class="expense-timeline-meta">
                    <?php if ($status === 'pending'): ?>
                        Admin review pending
                    <?php else: ?>
                        <?= e($reviewerName !== '' ? $reviewerName : 'Admin') ?>
                        <?= $reviewedAt !== '' ? ' · ' . e(date('M d, Y g:i A', strtotime($reviewedAt))) : '' ?>
                    <?php endif; ?>
                </span>
                <?php if ($adminNote !== ''): ?>
                    <span class="expense-timeline-note"><?= e($adminNote) ?></span>
                <?php endif; ?>
            </span>
        </li>
    </ol>
    <?php
}

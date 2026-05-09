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
    return (new Involve\Repositories\ExpenseRequestRepository($db))->all($filters);
}

function getExpenseRequestById(PDO $db, int $requestId): ?array
{
    return (new Involve\Repositories\ExpenseRequestRepository($db))->find($requestId);
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
    $requestId = (new Involve\Repositories\ExpenseRequestRepository($db))->create($organizationId, (int) $budget['id'], $budgetLineItemId, $requestedBy, $amount, $description, $receiptPath);
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

    try {
        $repository = new Involve\Repositories\ExpenseRequestRepository($db);
        $transactionId = withTransaction($db, static function () use ($db, $repository, $requestId, $adminNote, $reviewedBy): int {
            $request = $repository->lockForDecision($requestId);
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

            $transactionId = $repository->createExpenseTransactionForRequest($request, $requestId);
            $repository->markApproved($requestId, $reviewedBy, $transactionId, $adminNote);
            $repository->incrementLineSpent((int) $request['budget_line_item_id'], (float) $request['amount']);

            return $transactionId;
        });
        auditLog($reviewedBy, 'budget.expense_request_approve', 'expense_request', $requestId, 'Approved expense request and created transaction #' . $transactionId . ': ' . $adminNote);
        queueBudgetExpenseRequestDecisionNotification($db, $requestId, 'budget_expense_request_approved', $adminNote);

        return $transactionId;
    } catch (Throwable $e) {
        throw $e;
    }
}

function rejectExpenseRequest(PDO $db, int $requestId, int $reviewedBy, string $adminNote): void
{
    $adminNote = trim($adminNote);
    if ($requestId <= 0 || $reviewedBy <= 0 || $adminNote === '') {
        throw new InvalidArgumentException('A rejection note is required.');
    }

    try {
        $repository = new Involve\Repositories\ExpenseRequestRepository($db);
        withTransaction($db, static function () use ($repository, $requestId, $adminNote, $reviewedBy): void {
            $request = $repository->lockForDecision($requestId);
            if (!$request) {
                throw new RuntimeException('Expense request not found.');
            }

            if ((string) ($request['status'] ?? '') !== 'pending') {
                throw new RuntimeException('Only pending expense requests can be rejected.');
            }

            $repository->markRejected($requestId, $reviewedBy, $adminNote);
        });
        auditLog($reviewedBy, 'budget.expense_request_reject', 'expense_request', $requestId, 'Rejected expense request: ' . $adminNote);
        queueBudgetExpenseRequestDecisionNotification($db, $requestId, 'budget_expense_request_rejected', $adminNote);
    } catch (Throwable $e) {
        throw $e;
    }
}

function lockExpenseRequestForDecision(PDO $db, int $requestId): ?array
{
    return (new Involve\Repositories\ExpenseRequestRepository($db))->lockForDecision($requestId);
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

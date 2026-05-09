<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - BUDGETING HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. Budget Fetchers
 * 2. Budget Line Fetchers
 * 3. Allocation Calculations
 * 4. Budget Mutations
 *
 * WORK GUIDE:
 * - Keep page rendering and POST redirects outside this file.
 * - Reuse these helpers from owner pages, admin pages, and workflows.
 * ================================================
 */

function getOrganizationBudgets(PDO $db, int $organizationId): array
{
    return (new Involve\Repositories\BudgetRepository($db))->forOrganization($organizationId);
}

function getBudgetById(PDO $db, int $budgetId, ?int $organizationId = null): ?array
{
    return (new Involve\Repositories\BudgetRepository($db))->find($budgetId, $organizationId);
}

function getActiveOrganizationBudget(PDO $db, int $organizationId, ?string $date = null): ?array
{
    return (new Involve\Repositories\BudgetRepository($db))->activeForOrganization($organizationId, $date);
}

function getBudgetLineItems(PDO $db, int $budgetId, bool $withUsage = true): array
{
    return (new Involve\Repositories\BudgetRepository($db))->lineItems($budgetId, $withUsage);
}

function getBudgetLineItemById(PDO $db, int $lineItemId, ?int $budgetId = null): ?array
{
    return (new Involve\Repositories\BudgetRepository($db))->lineItem($lineItemId, $budgetId);
}

function getBudgetLinePendingAmount(PDO $db, int $lineItemId, ?int $excludeRequestId = null): float
{
    return (new Involve\Repositories\BudgetRepository($db))->pendingLineAmount($lineItemId, $excludeRequestId);
}

function getBudgetLineRemainingAmount(PDO $db, array $lineItem, bool $reservePending = true, ?int $excludeRequestId = null): float
{
    return (new Involve\Repositories\BudgetRepository($db))->remainingLineAmount($lineItem, $reservePending, $excludeRequestId);
}

function hydrateBudgetLineUsage(PDO $db, array $lineItem): array
{
    return (new Involve\Repositories\BudgetRepository($db))->hydrateLineUsage($lineItem);
}

function createBudget(PDO $db, int $organizationId, ?int $createdBy, string $title, string $periodStart, string $periodEnd, float $totalAmount, string $status = 'draft'): int
{
    return (new Involve\Repositories\BudgetRepository($db))->create($organizationId, $createdBy, $title, $periodStart, $periodEnd, $totalAmount, $status);
}

function createBudgetLineItem(PDO $db, int $budgetId, string $categoryName, ?string $description, float $allocatedAmount): int
{
    return (new Involve\Repositories\BudgetRepository($db))->createLineItem($budgetId, $categoryName, $description, $allocatedAmount);
}

function updateBudgetStatus(PDO $db, int $budgetId, string $status): void
{
    (new Involve\Repositories\BudgetRepository($db))->updateStatus($budgetId, $status);
}

function normalizeBudgetDate(?string $date): ?string
{
    $date = trim((string) $date);
    if ($date === '') {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return null;
    }

    return $date;
}

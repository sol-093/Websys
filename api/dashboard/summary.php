<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
$user = apiRequireUser();

$organizations = new Involve\Repositories\OrganizationRepository($db);
$visible = applyOrganizationVisibilityForUser(
    $organizations->allWithOwnerNames(),
    $user,
    $organizations->membershipIdsForUser((int) $user['id'])
);
$ids = array_map(static fn(array $org): int => (int) $org['id'], $visible);
$pagination = apiListParams();

if ($ids === []) {
    ApiList::send([], 0, $pagination);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("SELECT o.id, o.name,
    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense
    FROM organizations o
    LEFT JOIN financial_transactions t ON t.organization_id = o.id
    WHERE o.id IN ($placeholders)
    GROUP BY o.id, o.name
    ORDER BY o.name");
$stmt->execute($ids);
$items = $stmt->fetchAll() ?: [];

ApiList::send(array_slice($items, $pagination['offset'], $pagination['per_page']), count($items), $pagination);

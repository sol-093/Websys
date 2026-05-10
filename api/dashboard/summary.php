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

$items = array_map(
    static fn(array $row): array => [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'total_income' => (float) $row['total_income'],
        'total_expense' => (float) $row['total_expense'],
    ],
    Involve\Repositories\DashboardRepository::fromConnection($db)->aggregateSections($ids)['summary']
);

ApiList::send(array_slice($items, $pagination['offset'], $pagination['per_page']), count($items), $pagination);

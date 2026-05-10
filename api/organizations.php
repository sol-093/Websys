<?php

declare(strict_types=1);

use Involve\Repositories\OrganizationRepository;
use Involve\Support\ApiRequest;
use Involve\Support\JsonResponse;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/list_helpers.php';

ApiRequest::requireMethod('GET');

$user = apiRequireUser();
$organizations = new OrganizationRepository($db);
$q = apiSearchTerm();
$pagination = apiListParams();
$directory = $organizations->visibleDirectoryForUser($user, $q, $pagination['per_page'], $pagination['offset']);
$items = $directory['items'];
$total = $directory['total'];

JsonResponse::ok([
    'items' => $items,
    'organizations' => $items,
    'pagination' => [
        'page' => (int) $pagination['page'],
        'per_page' => (int) $pagination['per_page'],
        'total' => $total,
        'total_pages' => max(1, (int) ceil($total / max(1, (int) $pagination['per_page']))),
    ],
    'filters' => [
        'q' => $q,
        'sort' => (string) ($_GET['sort'] ?? 'name'),
    ],
]);

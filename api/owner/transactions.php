<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
$user = apiRequirePermission('manage_own_organization');
$organizationId = (int) ($_GET['organization_id'] ?? 0);
$organization = getOwnedOrganizationById((int) $user['id'], $organizationId);
if (!$organization) {
    Involve\Support\JsonResponse::error('Organization not found for this owner.', 404);
}

$pagination = apiListParams();
$transactions = (new Involve\Repositories\TransactionRepository($db))->listForOrganization($organizationId, $pagination['per_page'], $pagination['offset']);

ApiList::send($transactions['items'], $transactions['total'], $pagination, ['organization_id' => $organizationId]);

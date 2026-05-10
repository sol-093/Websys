<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
$user = apiRequirePermission('manage_own_organization');
$organizationId = (int) ($_GET['organization_id'] ?? 0);
if (!getOwnedOrganizationById((int) $user['id'], $organizationId)) {
    Involve\Support\JsonResponse::error('Organization not found for this owner.', 404);
}

$pagination = apiListParams();
$status = (string) ($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'declined', 'all'], true)) {
    $status = 'pending';
}
$requests = (new Involve\Repositories\OrganizationRepository($db))->joinRequestList($organizationId, $status, $pagination['per_page'], $pagination['offset']);

ApiList::send($requests['items'], $requests['total'], $pagination, ['organization_id' => $organizationId, 'status' => $status]);

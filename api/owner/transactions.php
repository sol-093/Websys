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
$countStmt = $db->prepare('SELECT COUNT(*) FROM financial_transactions WHERE organization_id = ?');
$countStmt->execute([$organizationId]);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM financial_transactions
    WHERE organization_id = ?
    ORDER BY transaction_date DESC, id DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute([$organizationId]);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['organization_id' => $organizationId]);

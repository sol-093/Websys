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
$whereStatus = $status === 'all' ? '' : ' AND ojr.status = ?';
$params = $status === 'all' ? [$organizationId] : [$organizationId, $status];

$countStmt = $db->prepare("SELECT COUNT(*) FROM organization_join_requests ojr WHERE ojr.organization_id = ?$whereStatus");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT ojr.*, u.name, u.email, u.institute, u.program, u.year_level, u.section
    FROM organization_join_requests ojr
    JOIN users u ON u.id = ojr.user_id
    WHERE ojr.organization_id = ?$whereStatus
    ORDER BY ojr.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['organization_id' => $organizationId, 'status' => $status]);

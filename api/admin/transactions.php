<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
apiRequirePermission('approve_transactions');

$pagination = apiListParams();
$status = (string) ($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where = 'WHERE tcr.status = ?';
    $params[] = $status;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM transaction_change_requests tcr $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT tcr.*, o.name AS organization_name, u.name AS requested_by_name
    FROM transaction_change_requests tcr
    JOIN organizations o ON o.id = tcr.organization_id
    JOIN users u ON u.id = tcr.requested_by
    $where
    ORDER BY tcr.created_at DESC, tcr.id DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['status' => $status]);

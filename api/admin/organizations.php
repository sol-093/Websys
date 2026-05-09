<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
apiRequirePermission('manage_organizations');

$pagination = apiListParams();
$q = apiSearchTerm();
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE o.name LIKE ? OR u.name LIKE ?';
    $params = [apiLike($q), apiLike($q)];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM organizations o LEFT JOIN users u ON u.id = o.owner_id $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT o.*, u.name AS owner_name,
        (SELECT COUNT(*) FROM organization_members om WHERE om.organization_id = o.id) AS member_count,
        (SELECT COUNT(*) FROM organization_join_requests ojr WHERE ojr.organization_id = o.id AND ojr.status = 'pending') AS pending_join_count
    FROM organizations o
    LEFT JOIN users u ON u.id = o.owner_id
    $where
    ORDER BY o.name ASC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['q' => $q]);

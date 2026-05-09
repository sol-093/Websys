<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
apiRequirePermission('view_audit_logs');

$pagination = apiListParams();
$q = apiSearchTerm();
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE al.action LIKE ? OR al.entity_type LIKE ? OR u.name LIKE ?';
    $params = [apiLike($q), apiLike($q), apiLike($q)];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT al.*, u.name AS user_name
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    $where
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['q' => $q]);

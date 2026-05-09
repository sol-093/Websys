<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
apiRequireUser();

$pagination = apiListParams();
$q = apiSearchTerm();
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE t.description LIKE ? OR o.name LIKE ?';
    $params = [apiLike($q), apiLike($q)];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM financial_transactions t JOIN organizations o ON o.id = t.organization_id $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT t.*, o.name AS organization_name
    FROM financial_transactions t
    JOIN organizations o ON o.id = t.organization_id
    $where
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['q' => $q]);

<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/list_helpers.php';

ApiRequest::requireMethod('GET');
apiRequireUser();

$pagination = apiListParams();
$q = apiSearchTerm();
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$where = 'WHERE (a.expires_at IS NULL OR a.expires_at >= ?)';
$params = [$now];
if ($q !== '') {
    $where .= ' AND (a.title LIKE ? OR a.content LIKE ? OR o.name LIKE ?)';
    array_push($params, apiLike($q), apiLike($q), apiLike($q));
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM announcements a JOIN organizations o ON o.id = a.organization_id $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT a.*, o.name AS organization_name
    FROM announcements a
    JOIN organizations o ON o.id = a.organization_id
    $where
    ORDER BY a.is_pinned DESC, COALESCE(a.pinned_at, a.created_at) DESC, a.created_at DESC, a.id DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['q' => $q]);

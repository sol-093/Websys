<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
apiRequireUser();

$pagination = apiListParams();
$days = max(1, min(90, (int) ($_GET['days'] ?? 30)));
$cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d');

$countStmt = $db->prepare("SELECT COUNT(*) FROM (
    SELECT id FROM announcements WHERE created_at >= ?
    UNION ALL
    SELECT id FROM financial_transactions WHERE transaction_date >= ?
) activity_count");
$countStmt->execute([$cutoff, $cutoff]);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM (
    SELECT 'announcement' AS type, a.id, a.title AS label, a.created_at, a.organization_id, o.name AS organization_name
    FROM announcements a
    JOIN organizations o ON o.id = a.organization_id
    WHERE a.created_at >= ?
    UNION ALL
    SELECT 'transaction' AS type, t.id, t.description AS label, t.created_at, t.organization_id, o.name AS organization_name
    FROM financial_transactions t
    JOIN organizations o ON o.id = t.organization_id
    WHERE t.transaction_date >= ?
) activity
ORDER BY created_at DESC
LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute([$cutoff, $cutoff]);

ApiList::send($stmt->fetchAll() ?: [], $total, $pagination, ['days' => $days]);

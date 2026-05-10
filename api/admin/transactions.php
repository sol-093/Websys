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
$requests = (new Involve\Repositories\TransactionRepository($db))->changeRequestList($status, $pagination['per_page'], $pagination['offset']);

ApiList::send($requests['items'], $requests['total'], $pagination, ['status' => $status]);

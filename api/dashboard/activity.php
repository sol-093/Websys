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

$activity = Involve\Repositories\DashboardRepository::fromConnection($db)->activityList($cutoff, $pagination['per_page'], $pagination['offset']);

ApiList::send($activity['items'], $activity['total'], $pagination, ['days' => $days]);

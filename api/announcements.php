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
$announcements = (new Involve\Repositories\AnnouncementRepository($db))->activeList($now, $q, $pagination['per_page'], $pagination['offset']);

ApiList::send($announcements['items'], $announcements['total'], $pagination, ['q' => $q]);

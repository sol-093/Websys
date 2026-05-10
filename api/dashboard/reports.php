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
$reports = (new Involve\Repositories\TransactionRepository($db))->listWithOrganization($q, $pagination['per_page'], $pagination['offset']);

ApiList::send($reports['items'], $reports['total'], $pagination, ['q' => $q]);

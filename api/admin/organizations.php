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
$organizations = (new Involve\Repositories\OrganizationRepository($db))->adminList($q, $pagination['per_page'], $pagination['offset']);

ApiList::send($organizations['items'], $organizations['total'], $pagination, ['q' => $q]);

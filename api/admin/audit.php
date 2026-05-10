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
$audit = (new Involve\Repositories\AuditRepository($db))->list($q, $pagination['per_page'], $pagination['offset']);

ApiList::send($audit['items'], $audit['total'], $pagination, ['q' => $q]);

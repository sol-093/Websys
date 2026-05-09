<?php

declare(strict_types=1);

use Involve\Support\ApiList;
use Involve\Support\ApiRequest;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/list_helpers.php';

ApiRequest::requireMethod('GET');
$user = apiRequireUser();
$pagination = apiListParams();
$items = collectUserRequestUpdates((int) $user['id'], 30, 100);

ApiList::send(array_slice($items, $pagination['offset'], $pagination['per_page']), count($items), $pagination);

<?php

declare(strict_types=1);

use Involve\Support\ApiRequest;
use Involve\Support\JsonResponse;

require __DIR__ . '/bootstrap.php';

ApiRequest::requireMethod('GET');

JsonResponse::ok([
    'status' => 'healthy',
    'app' => 'INVOLVE',
    'csrf_token' => csrfToken(),
]);

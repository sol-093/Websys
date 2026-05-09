<?php

declare(strict_types=1);

use Involve\Support\ApiRequest;
use Involve\Support\JsonResponse;

require __DIR__ . '/bootstrap.php';

ApiRequest::requireMethod('GET');

$user = apiRequireUser();
unset($user['password_hash']);

JsonResponse::ok([
    'user' => $user,
]);

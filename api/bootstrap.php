<?php

declare(strict_types=1);

use Involve\Support\ApiRequest;
use Involve\Support\JsonResponse;

define('INVOLVE_API_REQUEST', true);

require dirname(__DIR__) . '/includes/bootstrap.php';

function apiCurrentUser(): ?array
{
    return currentUser();
}

function apiRequireUser(): array
{
    $user = apiCurrentUser();
    if (!$user) {
        JsonResponse::error('Unauthenticated.', 401);
    }

    return $user;
}

function apiRequirePermission(string $permission): array
{
    $user = apiRequireUser();
    if (!can($permission, $user)) {
        JsonResponse::error('Forbidden.', 403);
    }

    return $user;
}

function apiRequireCsrf(): void
{
    if (!verifyCsrfToken(ApiRequest::csrfToken())) {
        JsonResponse::error('Invalid CSRF token.', 419);
    }
}

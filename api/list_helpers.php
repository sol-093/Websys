<?php

declare(strict_types=1);

use Involve\Support\ApiList;

function apiListParams(): array
{
    return ApiList::paginationParams($_GET);
}

function apiSearchTerm(): string
{
    return trim((string) ($_GET['q'] ?? ''));
}

function apiSortValue(array $allowed, string $default): string
{
    $sort = (string) ($_GET['sort'] ?? $default);
    return in_array($sort, $allowed, true) ? $sort : $default;
}

function apiLike(string $term): string
{
    return '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
}

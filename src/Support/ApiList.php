<?php

declare(strict_types=1);

namespace Involve\Support;

final class ApiList
{
    /**
     * @return array{page:int, per_page:int, offset:int}
     */
    public static function paginationParams(array $source): array
    {
        $page = max(1, (int) ($source['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($source['per_page'] ?? 20)));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $filters
     */
    public static function send(array $items, int $total, array $pagination, array $filters = [], int $status = 200): never
    {
        JsonResponse::ok([
            'items' => $items,
            'pagination' => [
                'page' => (int) $pagination['page'],
                'per_page' => (int) $pagination['per_page'],
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / max(1, (int) $pagination['per_page']))),
            ],
            'filters' => $filters,
        ], $status);
    }
}

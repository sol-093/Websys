<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - PAGINATION HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. paginateArray()
 * 2. renderPagination()
 *
 * WORK GUIDE:
 * - Edit this file for pagination behavior or markup used across features.
 * ================================================
 */

function paginateArray(array $items, string $queryKey, int $perPage = 10): array
{
    $totalItems = count($items);
    $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
    $page = max(1, (int) ($_GET[$queryKey] ?? 1));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);

    return [
        'items' => $slice,
        'page' => $page,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'query_key' => $queryKey,
    ];
}

function renderPagination(array $pagination): void
{
    $totalPages = (int) ($pagination['total_pages'] ?? 1);
    if ($totalPages <= 1) {
        return;
    }

    $currentPage = (int) ($pagination['page'] ?? 1);
    $queryKey = (string) ($pagination['query_key'] ?? 'p');
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    $isDashboardPage = (string) ($_GET['page'] ?? '') === 'dashboard';
    $preserveScroll = $isDashboardPage && str_starts_with($queryKey, 'pg_dash_');
    $preserveScrollAttr = $preserveScroll ? ' data-preserve-scroll="1"' : '';
    $anchor = trim((string) ($pagination['anchor'] ?? ''));

    $buildUrl = static function (int $targetPage) use ($queryKey, $anchor): string {
        $params = $_GET;
        $params[$queryKey] = $targetPage;
        $url = '?' . http_build_query($params);
        if ($anchor !== '') {
            $url .= '#' . rawurlencode(ltrim($anchor, '#'));
        }

        return $url;
    };

    ?>
    <div class="mt-3 flex items-center gap-2 text-xs">
        <a class="pagination-control inline-flex items-center justify-center rounded border px-2.5 py-1.5 <?= $currentPage <= 1 ? 'opacity-40 pointer-events-none' : '' ?>" href="<?= e($buildUrl(max(1, $currentPage - 1))) ?>"<?= $preserveScrollAttr ?>><span class="icon-label justify-center"><?= uiIcon('prev', 'ui-icon ui-icon-sm') ?><span>Prev</span></span></a>
        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <a class="px-2 py-1 rounded border <?= $p === $currentPage ? 'bg-indigo-700 text-white border-indigo-700' : '' ?>" href="<?= e($buildUrl($p)) ?>"<?= $preserveScrollAttr ?>><?= $p ?></a>
        <?php endfor; ?>
        <a class="pagination-control inline-flex items-center justify-center rounded border px-2.5 py-1.5 <?= $currentPage >= $totalPages ? 'opacity-40 pointer-events-none' : '' ?>" href="<?= e($buildUrl(min($totalPages, $currentPage + 1))) ?>"<?= $preserveScrollAttr ?>><span class="icon-label justify-center"><span>Next</span><?= uiIcon('next', 'ui-icon ui-icon-sm') ?></span></a>
        <span class="text-gray-500 ml-1">Page <?= $currentPage ?> of <?= $totalPages ?></span>
    </div>
    <?php
}

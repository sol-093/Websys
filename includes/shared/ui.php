<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - SHARED UI HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. renderBreadcrumb()
 * 2. renderEmptyState()
 * 3. renderSkeletonDashboard()
 *
 * WORK GUIDE:
 * - Edit this file for shared UI fragments used by multiple features.
 * ================================================
 */

function renderBreadcrumb(array $crumbs): void
{
    if ($crumbs === []) {
        return;
    }

    echo '<nav aria-label="breadcrumb" class="mb-3">';
    echo '<ol class="flex items-center gap-2 text-xs text-slate-500 whitespace-nowrap overflow-x-auto">';

    $lastIndex = count($crumbs) - 1;
    foreach ($crumbs as $index => $crumb) {
        $label = e((string) ($crumb['label'] ?? ''));
        $url = $crumb['url'] ?? null;

        echo '<li class="inline-flex items-center gap-2">';
        if ($url !== null && $url !== '') {
            echo '<a href="' . e((string) $url) . '" class="hover:text-slate-700">' . $label . '</a>';
        } else {
            echo '<span class="text-slate-700" aria-current="page">' . $label . '</span>';
        }

        if ($index < $lastIndex) {
            echo '<span aria-hidden="true" class="text-slate-400">/</span>';
        }

        echo '</li>';
    }

    echo '</ol>';
    echo '</nav>';
}

function renderEmptyState(string $icon, string $title, string $message, ?string $actionLabel = null, ?string $actionUrl = null): void
{
    $iconName = match ($icon) {
        'search' => 'search',
        'folder' => 'folder',
        'users' => 'students',
        'chart' => 'chart',
        default => 'mail',
    };
    $iconMarkup = uiIcon($iconName, 'ui-icon', true);

    echo '<div class="glass empty-state">';
    echo '<div class="empty-state-icon">' . $iconMarkup . '</div>';
    echo '<h3 class="empty-state-title">' . e($title) . '</h3>';
    echo '<p class="empty-state-message">' . e($message) . '</p>';

    if ($actionLabel !== null && $actionLabel !== '' && $actionUrl !== null && $actionUrl !== '') {
        echo '<a href="' . e($actionUrl) . '" class="empty-state-action">' . e($actionLabel) . '</a>';
    }

    echo '</div>';
}

function renderSkeletonDashboard(): void
{
    echo '<div class="dashboard-shell space-y-3" aria-hidden="true">';

    echo '<section class="grid xl:grid-cols-12 gap-3">';
    echo '<div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-24"></div>';
    echo '<div class="skeleton skeleton-text w-full max-w-2xl h-7"></div>';
    echo '<div class="skeleton skeleton-text w-full max-w-xl"></div>';
    echo '</div>';
    echo '<div class="dashboard-metric-grid mt-4">';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-36"></div>';
    echo '<div class="skeleton skeleton-text w-full max-w-sm"></div>';
    echo '</div>';
    echo '<div class="mt-4 space-y-3">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-4/5"></div>';
    echo '<div class="skeleton skeleton-text w-3/4"></div>';
    echo '<div class="skeleton skeleton-text w-2/3"></div>';
    echo '</div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="grid xl:grid-cols-12 gap-3">';
    echo '<div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-40"></div>';
    echo '<div class="skeleton skeleton-text w-64"></div>';
    echo '</div>';
    echo '<div class="dashboard-metric-grid mb-3">';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '</div>';
    echo '<div class="skeleton skeleton-card"></div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-28"></div>';
    echo '<div class="skeleton skeleton-text w-52"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-5/6"></div>';
    echo '<div class="skeleton skeleton-text w-4/5"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-32"></div>';
    echo '<div class="skeleton skeleton-text w-60"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-3/4"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-36"></div>';
    echo '<div class="skeleton skeleton-text w-64"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-5/6"></div>';
    echo '<div class="skeleton skeleton-text w-3/4"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-12 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-56"></div>';
    echo '<div class="skeleton skeleton-text w-80"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-11/12"></div>';
    echo '</div>';
    echo '</div>';
    echo '</section>';

    echo '</div>';
}

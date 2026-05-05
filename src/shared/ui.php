<?php

declare(strict_types=1);

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
    $iconMarkup = match ($icon) {
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M16 16l5 5"></path></svg>',
        'folder' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7.5a2 2 0 012-2h4l1.8 2H18.5a2 2 0 012 2v8a2 2 0 01-2 2H5.5a2 2 0 01-2-2z"></path></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="8" cy="9" r="2.5"></circle><circle cx="16" cy="10" r="2.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 18c.8-2.2 2.8-3.5 5.1-3.5S12.9 15.8 13.7 18"></path><path stroke-linecap="round" stroke-linejoin="round" d="M13 18c.6-1.8 2.1-2.9 3.9-2.9 1.8 0 3.3 1.1 3.9 2.9"></path></svg>',
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5h16"></path><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V10"></path><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V6"></path><path stroke-linecap="round" stroke-linejoin="round" d="M17 16v-3"></path></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5a2 2 0 012-2h12a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 8l7.5 5 7.5-5"></path></svg>',
    };

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

<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class DashboardRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly OrganizationRepository $organizations,
        private readonly TransactionRepository $transactions
    ) {
    }

    public static function fromConnection(PDO $db): self
    {
        return new self($db, new OrganizationRepository($db), new TransactionRepository($db));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function organizationsForDashboard(): array
    {
        return $this->remember('organizations:directory:all_with_owner_names', 60, fn() => $this->organizations->allWithOwnerNames());
    }

    /**
     * @return list<int>
     */
    public function membershipIdsForUser(int $userId): array
    {
        return $this->organizations->membershipIdsForUser($userId);
    }

    /**
     * @return array<int, string>
     */
    public function joinRequestStatusForUser(int $userId): array
    {
        return $this->organizations->joinRequestStatusForUser($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeAnnouncements(string $activeCutoff, int $limit = 30): array
    {
        $cacheBucket = substr($activeCutoff, 0, 16);

        return $this->remember('dashboard:active_announcements:' . $cacheBucket . ':' . $limit, 60, fn() => (new AnnouncementRepository($this->db))->activeWithOrganization($activeCutoff, $limit));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransactions(string $fromDate, int $limit = 8): array
    {
        return $this->remember('dashboard:recent_transactions:' . $fromDate . ':' . $limit, 60, fn() => $this->transactions->recentWithOrganization($fromDate, $limit));
    }

    /**
     * @return array<string, mixed>|false
     */
    public function financialTotals(): array|false
    {
        return $this->remember('dashboard:financial_totals', 60, fn() => $this->transactions->totalsByType());
    }

    private function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return function_exists('cacheRemember') ? \cacheRemember($key, $ttlSeconds, $callback) : $callback();
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}

<?php

declare(strict_types=1);

namespace Involve\Support;

final class QueryProfiler
{
    public function __construct(
        private readonly bool $enabled,
        private readonly int $slowQueryMs = 150
    ) {
    }

    public function profile(string $label, callable $callback): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        $startedAt = microtime(true);

        try {
            return $callback();
        } finally {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            if ($durationMs >= $this->slowQueryMs) {
                error_log(sprintf(
                    '[query-profile] label=%s duration_ms=%d route=%s page=%s action=%s',
                    preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $label),
                    $durationMs,
                    (string) ($_SERVER['REQUEST_URI'] ?? 'cli'),
                    (string) ($_GET['page'] ?? ''),
                    (string) ($_POST['action'] ?? $_GET['action'] ?? '')
                ));
            }
        }
    }
}

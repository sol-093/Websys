<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - MAINTENANCE HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. Expired Announcement Cleanup
 *
 * WORK GUIDE:
 * - Edit this file for scheduled/runtime cleanup helpers.
 * ================================================
 */

function purgeExpiredAnnouncements(PDO $db, int $days = 30): void
{
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('DELETE FROM announcements WHERE (expires_at IS NOT NULL AND expires_at < ?) OR (expires_at IS NULL AND created_at < ?)');
    $stmt->execute([$now, $cutoff]);
}

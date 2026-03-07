<?php

declare(strict_types=1);

function purgeExpiredAnnouncements(PDO $db, int $days = 30): void
{
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    $stmt = $db->prepare('DELETE FROM announcements WHERE created_at < ?');
    $stmt->execute([$cutoff]);
}

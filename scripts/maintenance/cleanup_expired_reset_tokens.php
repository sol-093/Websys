<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/core/helpers.php';
require dirname(__DIR__, 2) . '/includes/core/db.php';

$db = db();
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

$stmt = $db->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE reset_token IS NOT NULL AND reset_expires IS NOT NULL AND reset_expires < ?');
$stmt->execute([$now]);

$count = $stmt->rowCount();

echo 'Expired reset tokens cleared: ' . $count . PHP_EOL;

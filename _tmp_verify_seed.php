<?php
require __DIR__ . '/src/core/db.php';
$pdo = db();
$users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$orgs = (int)$pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
$tx = (int)$pdo->query('SELECT COUNT(*) FROM financial_transactions')->fetchColumn();
echo "users={$users}\n";
echo "organizations={$orgs}\n";
echo "financial_transactions={$tx}\n";

<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class AuditRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(string $query = '', int $limit = 25, int $offset = 0): array
    {
        return $this->profile('audit.list', function () use ($query, $limit, $offset): array {
            $where = '';
            $params = [];
            $query = trim($query);
            if ($query !== '') {
                $where = 'WHERE al.action LIKE ? OR al.entity_type LIKE ? OR u.name LIKE ?';
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
                $params = [$like, $like, $like];
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id $where");
            $countStmt->execute($params);

            $stmt = $this->db->prepare("SELECT al.*, u.name AS user_name
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                $where
                ORDER BY al.created_at DESC, al.id DESC
                LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute($params);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function userTrail(int $userId, string $cutoff, int $limit = 25): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->profile('audit.user_trail', function () use ($userId, $cutoff, $limit): array {
            $stmt = $this->db->prepare(
                'SELECT action, entity_type, entity_id, details, ip_address, user_agent, created_at
                 FROM audit_logs
                 WHERE user_id = ? AND created_at >= ?
                 ORDER BY id DESC
                 LIMIT ' . max(1, $limit)
            );
            $stmt->execute([$userId, $cutoff]);

            return $stmt->fetchAll() ?: [];
        });
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}

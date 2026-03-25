<?php

declare(strict_types=1);

final class ReturnsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getReturns(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, return_no, product_id, quantity, reason, status, created_at
             FROM returns
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM returns')->fetch()['total'];
    }

    public function createReturn(array $data): int
    {
        $returnNo = 'RET-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO returns (return_no, product_id, quantity, reason, status, created_at) VALUES (:no, :product_id, :qty, :reason, :status, NOW())'
        );
        $stmt->execute([
            ':no' => $returnNo,
            ':product_id' => (int)$data['product_id'],
            ':qty' => (int)$data['quantity'],
            ':reason' => trim($data['reason'] ?? ''),
            ':status' => $data['status'] ?? 'Pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

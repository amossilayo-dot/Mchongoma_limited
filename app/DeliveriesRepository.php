<?php

declare(strict_types=1);

final class DeliveriesRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getDeliveries(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, delivery_no, customer_id, status, amount, created_at
             FROM deliveries
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM deliveries')->fetch()['total'];
    }

    public function createDelivery(array $data): int
    {
        $deliveryNo = 'DEL-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO deliveries (delivery_no, customer_id, status, amount, created_at) VALUES (:no, :customer_id, :status, :amount, NOW())'
        );
        $stmt->execute([
            ':no' => $deliveryNo,
            ':customer_id' => (int)$data['customer_id'],
            ':status' => $data['status'] ?? 'Pending',
            ':amount' => (float)$data['amount'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

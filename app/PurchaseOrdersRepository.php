<?php

declare(strict_types=1);

final class PurchaseOrdersRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getOrders(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, po_no, supplier_id, amount, status, created_at
             FROM purchase_orders
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM purchase_orders')->fetch()['total'];
    }

    public function createOrder(array $data): int
    {
        $poNo = 'PO-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_orders (po_no, supplier_id, amount, status, created_at) VALUES (:no, :supplier_id, :amount, :status, NOW())'
        );
        $stmt->execute([
            ':no' => $poNo,
            ':supplier_id' => (int)$data['supplier_id'],
            ':amount' => (float)$data['amount'],
            ':status' => $data['status'] ?? 'Pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

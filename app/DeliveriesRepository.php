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
            'SELECT d.id,
                    d.delivery_no,
                    d.customer_id,
                    c.name AS customer_name,
                    d.status,
                    d.amount,
                    d.created_at
             FROM deliveries d
             LEFT JOIN customers c ON c.id = d.customer_id
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
        $customerId = (int) ($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please select a valid customer.');
        }

        $customerExistsStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM customers WHERE id = :id');
        $customerExistsStmt->execute([':id' => $customerId]);
        $customerExists = (int) ($customerExistsStmt->fetch()['total'] ?? 0) > 0;
        if (!$customerExists) {
            throw new InvalidArgumentException('Selected customer does not exist.');
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Delivery amount must be greater than zero.');
        }

        $deliveryNo = 'DEL-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO deliveries (delivery_no, customer_id, status, amount, created_at) VALUES (:no, :customer_id, :status, :amount, NOW())'
        );
        $stmt->execute([
            ':no' => $deliveryNo,
            ':customer_id' => $customerId,
            ':status' => $data['status'] ?? 'Pending',
            ':amount' => $amount,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateDeliveryStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            return false;
        }

        $requestedRaw = strtolower(trim($status));
        $statusMap = [
            'in transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
        $normalizedStatus = $statusMap[$requestedRaw] ?? null;
        if ($normalizedStatus === null) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT status
             FROM deliveries
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $currentRaw = strtolower(trim((string) ($row['status'] ?? 'pending')));
        if ($currentRaw === '') {
            $currentRaw = 'pending';
        }

        $currentMap = [
            'pending' => 'Pending',
            'in transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
        $currentStatus = $currentMap[$currentRaw] ?? null;
        if ($currentStatus === null) {
            return false;
        }

        $transitions = [
            'Pending' => ['In Transit', 'Cancelled'],
            'In Transit' => ['Delivered', 'Cancelled'],
            'Delivered' => [],
            'Cancelled' => [],
        ];

        $allowedNext = $transitions[$currentStatus] ?? [];
        if (!in_array($normalizedStatus, $allowedNext, true)) {
            return false;
        }

        $updateStmt = $this->pdo->prepare(
            'UPDATE deliveries
             SET status = :status
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':status' => $normalizedStatus,
            ':id' => $id,
        ]);

        return $updateStmt->rowCount() > 0;
    }
}

<?php

declare(strict_types=1);

final class ReceivingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getReceivings(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, receiving_no, supplier_id, status, amount, created_at
             FROM receiving
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM receiving')->fetch()['total'];
    }

    public function createReceiving(array $data): int
    {
        $receivingNo = 'RCV-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO receiving (receiving_no, supplier_id, status, amount, created_at) VALUES (:no, :supplier_id, :status, :amount, NOW())'
        );
        $stmt->execute([
            ':no' => $receivingNo,
            ':supplier_id' => (int)$data['supplier_id'],
            ':status' => $data['status'] ?? 'Pending',
            ':amount' => (float)$data['amount'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

<?php

declare(strict_types=1);

final class QuotationsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getQuotations(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, quotation_no, customer_id, amount, status, created_at
             FROM quotations
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM quotations')->fetch()['total'];
    }

    public function createQuotation(array $data): int
    {
        $quotationNo = 'QT-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO quotations (quotation_no, customer_id, amount, status, created_at) VALUES (:no, :customer_id, :amount, :status, NOW())'
        );
        $stmt->execute([
            ':no' => $quotationNo,
            ':customer_id' => (int)$data['customer_id'],
            ':amount' => (float)$data['amount'],
            ':status' => $data['status'] ?? 'Pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

<?php

declare(strict_types=1);

final class InvoicesRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getInvoices(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.id, i.invoice_no, i.customer_id, c.name AS customer_name, i.amount, i.status, i.created_at
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             ORDER BY i.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM invoices')->fetch()['total'];
    }

    public function createInvoice(array $data): int
    {
        $invoiceNo = 'INV-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO invoices (invoice_no, customer_id, amount, status, created_at) VALUES (:no, :customer_id, :amount, :status, NOW())'
        );
        $stmt->execute([
            ':no' => $invoiceNo,
            ':customer_id' => (int)$data['customer_id'],
            ':amount' => (float)$data['amount'],
            ':status' => $data['status'] ?? 'Pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateInvoice(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invoices SET customer_id = :customer_id, amount = :amount, status = :status WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':customer_id' => (int)$data['customer_id'],
            ':amount' => (float)$data['amount'],
            ':status' => $data['status'],
        ]);
    }

    public function deleteInvoice(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM invoices WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}

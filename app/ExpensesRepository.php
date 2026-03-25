<?php

declare(strict_types=1);

final class ExpensesRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getExpenses(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, description, category, amount, status, created_at
             FROM expenses
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM expenses')->fetch()['total'];
    }

    public function createExpense(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO expenses (description, category, amount, status, created_at) VALUES (:desc, :category, :amount, :status, NOW())'
        );
        $stmt->execute([
            ':desc' => trim($data['description'] ?? ''),
            ':category' => trim($data['category'] ?? ''),
            ':amount' => (float)($data['amount'] ?? 0),
            ':status' => $data['status'] ?? 'Pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateExpense(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE expenses SET description = :desc, category = :category, amount = :amount, status = :status WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':desc' => trim($data['description'] ?? ''),
            ':category' => trim($data['category'] ?? ''),
            ':amount' => (float)($data['amount'] ?? 0),
            ':status' => $data['status'] ?? 'Pending',
        ]);
    }

    public function deleteExpense(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM expenses WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getTotalByCategory(): array
    {
        $stmt = $this->pdo->query(
            'SELECT category, SUM(amount) AS total FROM expenses WHERE status = "Approved" GROUP BY category'
        );
        return $stmt->fetchAll();
    }
}

<?php

declare(strict_types=1);

final class CustomerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getCustomers(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.phone, c.created_at,
                    COUNT(s.id) AS total_orders,
                    COALESCE(SUM(s.amount), 0) AS total_spent
             FROM customers c
             LEFT JOIN sales s ON s.customer_id = c.id
             GROUP BY c.id
             ORDER BY c.name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM customers')->fetch()['total'];
    }

    public function getCustomer(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createCustomer(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (name, phone) VALUES (:name, :phone)'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCustomer(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE customers SET name = :name, phone = :phone WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?? null,
        ]);
    }

    public function deleteCustomer(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getCustomerSales(int $customerId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT transaction_no, amount, payment_method, created_at
             FROM sales WHERE customer_id = :id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

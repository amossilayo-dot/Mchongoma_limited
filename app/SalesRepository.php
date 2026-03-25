<?php

declare(strict_types=1);

final class SalesRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getSales(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.transaction_no, s.amount, s.payment_method, s.created_at,
                    c.name AS customer_name
             FROM sales s
             JOIN customers c ON c.id = s.customer_id
             ORDER BY s.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM sales')->fetch()['total'];
    }

    public function getTodaysSales(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.transaction_no, s.amount, s.payment_method, s.created_at,
                    c.name AS customer_name
             FROM sales s
             JOIN customers c ON c.id = s.customer_id
             WHERE DATE(s.created_at) = CURDATE()
             ORDER BY s.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getSale(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, c.name AS customer_name, c.phone AS customer_phone
             FROM sales s
             JOIN customers c ON c.id = s.customer_id
             WHERE s.id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createSale(array $data): int
    {
        $transactionNo = 'TXN-' . date('Ymd-His') . rand(100, 999) . '-' . rand(100, 999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales (transaction_no, customer_id, amount, payment_method, created_at)
             VALUES (:transaction_no, :customer_id, :amount, :payment_method, NOW())'
        );
        $stmt->execute([
            ':transaction_no' => $transactionNo,
            ':customer_id' => $data['customer_id'],
            ':amount' => $data['amount'],
            ':payment_method' => $data['payment_method'] ?? 'Cash',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getMonthlySales(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS sale_date, SUM(amount) AS total
             FROM sales
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY sale_date ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['sale_date']] = (float) $row['total'];
        }

        $labels = [];
        $values = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'));
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d');
            $values[] = $indexed[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    public function getSalesSummary(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                COALESCE(SUM(amount), 0) AS total_sales,
                COUNT(*) AS total_transactions,
                COALESCE(AVG(amount), 0) AS average_sale
             FROM sales'
        );
        return $stmt->fetch();
    }

    public function getTodaySummary(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(amount), 0) AS total_sales,
                COUNT(*) AS total_transactions,
                COALESCE(AVG(amount), 0) AS average_sale
             FROM sales
             WHERE DATE(created_at) = CURDATE()'
        );
        $stmt->execute();
        return $stmt->fetch();
    }
}

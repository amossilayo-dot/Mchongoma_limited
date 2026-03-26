<?php

declare(strict_types=1);

final class DashboardRepository
{
    private ?array $productColumnMap = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function getTotals(): array
    {
        $columns = $this->resolveProductColumnMap();
        $quantityColumn = $columns['quantity'];

        $totalSales = (float) $this->pdo->query('SELECT COALESCE(SUM(amount), 0) AS total FROM sales')->fetch()['total'];
        $totalCustomers = (int) $this->pdo->query('SELECT COUNT(*) AS total FROM customers')->fetch()['total'];
        $totalProducts = (int) $this->pdo->query('SELECT COUNT(*) AS total FROM products')->fetch()['total'];
        $totalStockUnits = (int) $this->pdo->query('SELECT COALESCE(SUM(' . $quantityColumn . '), 0) AS total FROM products')->fetch()['total'];

        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM sales WHERE DATE(created_at) = CURDATE()');
        $stmt->execute();
        $transactionsToday = (int) $stmt->fetch()['total'];

        return [
            'totalSales' => $totalSales,
            'totalCustomers' => $totalCustomers,
            'totalProducts' => $totalProducts,
            'totalStockUnits' => $totalStockUnits,
            'totalItems' => $totalStockUnits,
            'transactionsToday' => $transactionsToday,
        ];
    }

    public function getWeeklySales(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS sale_date, SUM(amount) AS total
             FROM sales
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
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

        for ($i = 6; $i >= 0; $i--) {
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

    public function getLowStockSummary(): array
    {
        $columns = $this->resolveProductColumnMap();
        $quantityColumn = $columns['quantity'];
        $reorderExpression = $columns['reorder'] !== null ? $columns['reorder'] : '5';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM products WHERE $quantityColumn <= $reorderExpression");
        $stmt->execute();
        $count = (int) $stmt->fetch()['total'];

        return [
            'count' => $count,
            'message' => $count > 0 ? $count . ' products need restocking.' : 'All products are well stocked.',
        ];
    }

    public function getRecentSales(int $limit = 4): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.transaction_no, s.amount, s.payment_method, s.created_at, c.name AS customer_name
             FROM sales s
             JOIN customers c ON c.id = s.customer_id
             ORDER BY s.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function resolveProductColumnMap(): array
    {
        if ($this->productColumnMap !== null) {
            return $this->productColumnMap;
        }

        $columns = [];
        foreach ($this->pdo->query('SHOW COLUMNS FROM products')->fetchAll() as $column) {
            $columns[(string) $column['Field']] = true;
        }

        $this->productColumnMap = [
            'quantity' => isset($columns['stock_qty']) ? 'stock_qty' : 'stock',
            'reorder' => isset($columns['reorder_level']) ? 'reorder_level' : null,
        ];

        return $this->productColumnMap;
    }
}

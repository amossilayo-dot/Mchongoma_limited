<?php

declare(strict_types=1);

final class MobileMoneyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getRecentTransactions(int $limit = 50): array
    {
        if (!$this->tableExists('mobile_money_transactions')) {
            return [];
        }

        $limit = max(1, min($limit, 200));

        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.sale_id, m.provider, m.msisdn, m.amount, m.currency, m.external_reference, m.status, m.created_at,
                    s.transaction_no,
                    c.name AS customer_name
             FROM mobile_money_transactions m
             LEFT JOIN sales s ON s.id = m.sale_id
             LEFT JOIN customers c ON c.id = s.customer_id
             ORDER BY m.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $tableName]);

        return ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    }
}

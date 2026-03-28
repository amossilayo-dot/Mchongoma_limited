<?php

declare(strict_types=1);

final class ReturnsRepository
{
    private ?string $productStockColumn = null;
    private ?bool $hasIsExpiredColumn = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function getReturns(int $limit = 50, int $offset = 0): array
    {
                $isExpiredSelect = $this->hasIsExpiredColumn()
                        ? 'r.is_expired AS is_expired'
                        : '0 AS is_expired';

        $stmt = $this->pdo->prepare(
                        'SELECT r.id, r.return_no, r.product_id, p.name AS product_name, r.quantity, r.reason, ' . $isExpiredSelect . ', r.status, r.created_at
             FROM returns r
             INNER JOIN products p ON p.id = r.product_id
               ORDER BY r.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM returns')->fetch()['total'];
    }

    public function createReturn(array $data): int
    {
        $productId = (int) ($data['product_id'] ?? 0);
        if ($productId <= 0) {
            throw new InvalidArgumentException('Please select a valid product.');
        }

        $quantity = (int) ($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $productExistsStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM products WHERE id = :id');
        $productExistsStmt->execute([':id' => $productId]);
        $productExists = (int) ($productExistsStmt->fetch()['total'] ?? 0) > 0;
        if (!$productExists) {
            throw new InvalidArgumentException('Selected product does not exist.');
        }

        $status = trim((string) ($data['status'] ?? 'Pending'));
        $allowedStatuses = ['Pending', 'Approved', 'Rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Pending';
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        $rawExpiredFlag = $data['is_expired'] ?? false;
        $isExpired = false;
        if (is_bool($rawExpiredFlag)) {
            $isExpired = $rawExpiredFlag;
        } else {
            $isExpired = in_array(
                strtolower(trim((string) $rawExpiredFlag)),
                ['1', 'true', 'yes', 'on'],
                true
            );
        }

        if ($isExpired) {
            if ($reason === '') {
                $reason = 'expired item';
            } elseif (preg_match('/\b(expired|expire|expiry|imeisha|imekwisha|bad)\b/i', $reason) !== 1) {
                $reason = 'expired item - ' . $reason;
            }
        }

        if ($reason !== '' && strlen($reason) > 2000) {
            throw new InvalidArgumentException('Reason must be 2000 characters or less.');
        }

        $returnNo = 'RET-' . date('Ymd') . '-' . random_int(1000, 9999);
        if ($this->hasIsExpiredColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO returns (return_no, product_id, quantity, reason, is_expired, status, created_at)
                 VALUES (:no, :product_id, :qty, :reason, :is_expired, :status, NOW())'
            );
            $stmt->execute([
                ':no' => $returnNo,
                ':product_id' => $productId,
                ':qty' => $quantity,
                ':reason' => $reason !== '' ? $reason : null,
                ':is_expired' => $isExpired ? 1 : 0,
                ':status' => $status,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO returns (return_no, product_id, quantity, reason, status, created_at) VALUES (:no, :product_id, :qty, :reason, :status, NOW())'
            );
            $stmt->execute([
                ':no' => $returnNo,
                ':product_id' => $productId,
                ':qty' => $quantity,
                ':reason' => $reason !== '' ? $reason : null,
                ':status' => $status,
            ]);
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function updateReturnStatus(int $id, string $status): array
    {
        if ($id <= 0) {
            return ['updated' => false, 'stock_applied' => false, 'skipped_expired' => false];
        }

        $normalizedStatus = trim($status);
        $allowedStatuses = ['Approved', 'Rejected'];
        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            return ['updated' => false, 'stock_applied' => false, 'skipped_expired' => false];
        }

        $this->pdo->beginTransaction();
        try {
            $isExpiredSelect = $this->hasIsExpiredColumn() ? ', is_expired' : '';
            $stmt = $this->pdo->prepare(
                'SELECT id, product_id, quantity, reason, status' . $isExpiredSelect . '
                 FROM returns
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $id]);
            $returnRow = $stmt->fetch();

            if (!$returnRow) {
                $this->pdo->rollBack();
                return ['updated' => false, 'stock_applied' => false, 'skipped_expired' => false];
            }

            $currentStatus = trim((string) ($returnRow['status'] ?? 'Pending'));
            if (strtolower($currentStatus) !== 'pending') {
                $this->pdo->rollBack();
                return ['updated' => false, 'stock_applied' => false, 'skipped_expired' => false];
            }

            $productId = (int) ($returnRow['product_id'] ?? 0);
            $quantity = max(0, (int) ($returnRow['quantity'] ?? 0));
            $reason = trim((string) ($returnRow['reason'] ?? ''));
            $isExpiredReturn = $this->hasIsExpiredColumn()
                ? ((int) ($returnRow['is_expired'] ?? 0)) === 1
                : (preg_match('/\b(expired|expire|expiry|imeisha|imekwisha|bad)\b/i', $reason) === 1);

            $stockApplied = false;
            if ($normalizedStatus === 'Approved' && !$isExpiredReturn && $productId > 0 && $quantity > 0) {
                $stockColumn = $this->resolveProductStockColumn();
                $stockStmt = $this->pdo->prepare(
                    'UPDATE products
                     SET ' . $stockColumn . ' = ' . $stockColumn . ' + :qty
                     WHERE id = :product_id'
                );
                $stockStmt->execute([
                    ':qty' => $quantity,
                    ':product_id' => $productId,
                ]);
                $stockApplied = true;
            }

            $updateStmt = $this->pdo->prepare(
                'UPDATE returns
                 SET status = :status
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':status' => $normalizedStatus,
                ':id' => $id,
            ]);

            $this->pdo->commit();
            return [
                'updated' => true,
                'stock_applied' => $stockApplied,
                'skipped_expired' => $normalizedStatus === 'Approved' && $isExpiredReturn,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function resolveProductStockColumn(): string
    {
        if (is_string($this->productStockColumn) && $this->productStockColumn !== '') {
            return $this->productStockColumn;
        }

        $stmt = $this->pdo->query('SHOW COLUMNS FROM products');
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
        $columns = [];
        foreach ($rows as $row) {
            $name = strtolower(trim((string) ($row['Field'] ?? '')));
            if ($name !== '') {
                $columns[$name] = true;
            }
        }

        foreach (['stock_qty', 'stock', 'quantity'] as $candidate) {
            if (isset($columns[$candidate])) {
                $this->productStockColumn = $candidate;
                return $candidate;
            }
        }

        throw new RuntimeException('Products table is missing a stock quantity column.');
    }

    private function hasIsExpiredColumn(): bool
    {
        if (is_bool($this->hasIsExpiredColumn)) {
            return $this->hasIsExpiredColumn;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = "returns"
               AND column_name = "is_expired"'
        );
        $stmt->execute();
        $this->hasIsExpiredColumn = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;

        return $this->hasIsExpiredColumn;
    }
}

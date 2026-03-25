<?php

declare(strict_types=1);

final class InventoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getProducts(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, sku, stock_qty, reorder_level, unit_price, created_at
             FROM products
             ORDER BY name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM products')->fetch()['total'];
    }

    public function getProduct(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createProduct(array $data): int
    {
        // Validate required fields
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Product name is required and must be 255 characters or less');
        }

        $sku = trim($data['sku'] ?? '');
        if ($sku === '' || strlen($sku) > 100 || !preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
            throw new InvalidArgumentException('SKU is required and must be alphanumeric (max 100 chars)');
        }

        $stockQty = $data['stock_qty'] ?? 0;
        if (!is_numeric($stockQty) || $stockQty < 0) {
            throw new InvalidArgumentException('Stock quantity must be a non-negative number');
        }

        $reorderLevel = $data['reorder_level'] ?? 5;
        if (!is_numeric($reorderLevel) || $reorderLevel < 0) {
            throw new InvalidArgumentException('Reorder level must be a non-negative number');
        }

        $unitPrice = $data['unit_price'] ?? 0;
        if (!is_numeric($unitPrice) || $unitPrice < 0) {
            throw new InvalidArgumentException('Unit price must be a non-negative number');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO products (name, sku, stock_qty, reorder_level, unit_price)
             VALUES (:name, :sku, :stock_qty, :reorder_level, :unit_price)'
        );
        $stmt->execute([
            ':name' => $name,
            ':sku' => $sku,
            ':stock_qty' => (int) $stockQty,
            ':reorder_level' => (int) $reorderLevel,
            ':unit_price' => (float) $unitPrice,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProduct(int $id, array $data): bool
    {
        // Validate required fields
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Product name is required and must be 255 characters or less');
        }

        $sku = trim($data['sku'] ?? '');
        if ($sku === '' || strlen($sku) > 100 || !preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
            throw new InvalidArgumentException('SKU is required and must be alphanumeric (max 100 chars)');
        }

        $stockQty = $data['stock_qty'] ?? 0;
        if (!is_numeric($stockQty) || $stockQty < 0) {
            throw new InvalidArgumentException('Stock quantity must be a non-negative number');
        }

        $reorderLevel = $data['reorder_level'] ?? 0;
        if (!is_numeric($reorderLevel) || $reorderLevel < 0) {
            throw new InvalidArgumentException('Reorder level must be a non-negative number');
        }

        $unitPrice = $data['unit_price'] ?? 0;
        if (!is_numeric($unitPrice) || $unitPrice < 0) {
            throw new InvalidArgumentException('Unit price must be a non-negative number');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE products SET name = :name, sku = :sku, stock_qty = :stock_qty,
             reorder_level = :reorder_level, unit_price = :unit_price WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':sku' => $sku,
            ':stock_qty' => (int) $stockQty,
            ':reorder_level' => (int) $reorderLevel,
            ':unit_price' => (float) $unitPrice,
        ]);
    }

    public function deleteProduct(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getLowStockProducts(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, sku, stock_qty, reorder_level, unit_price
             FROM products WHERE stock_qty <= reorder_level ORDER BY stock_qty ASC'
        );
        return $stmt->fetchAll();
    }

    public function importProductsFromRows(array $rows): array
    {
        $existsStmt = $this->pdo->prepare('SELECT sku FROM products WHERE sku = :sku LIMIT 1');
        $upsertStmt = $this->pdo->prepare(
            'INSERT INTO products (name, sku, stock_qty, reorder_level, unit_price)
             VALUES (:name, :sku, :stock_qty, :reorder_level, :unit_price)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                stock_qty = VALUES(stock_qty),
                reorder_level = VALUES(reorder_level),
                unit_price = VALUES(unit_price)'
        );

        $created = 0;
        $updated = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $existsStmt->execute([':sku' => $row['sku']]);
                $existing = (bool) $existsStmt->fetch();

                $upsertStmt->execute([
                    ':name' => $row['name'],
                    ':sku' => $row['sku'],
                    ':stock_qty' => $row['stock_qty'],
                    ':reorder_level' => $row['reorder_level'],
                    ':unit_price' => $row['unit_price'],
                ]);

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'processed' => count($rows),
        ];
    }
}

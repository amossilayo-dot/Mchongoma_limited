<?php

declare(strict_types=1);

final class InventoryRepository
{
    private ?array $productColumnMap = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function getProducts(int $limit = 50, int $offset = 0): array
    {
        $columns = $this->resolveProductColumnMap();
        $skuExpression = $columns['sku'] !== null ? $columns['sku'] : "CONCAT('SKU-', id)";
        $stockExpression = $columns['quantity'];
        $reorderExpression = $columns['reorder'] !== null ? $columns['reorder'] : '5';
        $priceExpression = $columns['price'];
        $categoryExpression = $columns['category'] !== null ? $columns['category'] : 'NULL';

        $stmt = $this->pdo->prepare(
            "SELECT id, name,
                    $skuExpression AS sku,
                    $categoryExpression AS category,
                    $stockExpression AS stock_qty,
                    $reorderExpression AS reorder_level,
                    $priceExpression AS unit_price,
                    created_at
             FROM products
             ORDER BY name ASC
             LIMIT :limit OFFSET :offset"
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
        $columns = $this->resolveProductColumnMap();

        // Validate required fields
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Product name is required and must be 255 characters or less');
        }

        $sku = trim($data['sku'] ?? '');
        if ($columns['sku'] !== null && ($sku === '' || strlen($sku) > 100 || !preg_match('/^[A-Za-z0-9\-_]+$/', $sku))) {
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

        $category = trim((string) ($data['category'] ?? ''));
        if ($category !== '' && strlen($category) > 100) {
            throw new InvalidArgumentException('Category must be 100 characters or less');
        }

        $columnNames = ['name'];
        $placeholders = [':name'];
        $params = [':name' => $name];

        if ($columns['sku'] !== null) {
            $columnNames[] = $columns['sku'];
            $placeholders[] = ':sku';
            $params[':sku'] = $sku;
        }

        if ($columns['category'] !== null) {
            $columnNames[] = $columns['category'];
            $placeholders[] = ':category';
            $params[':category'] = $category !== '' ? $category : null;
        }

        $columnNames[] = $columns['quantity'];
        $placeholders[] = ':quantity';
        $params[':quantity'] = (int) $stockQty;

        if ($columns['reorder'] !== null) {
            $columnNames[] = $columns['reorder'];
            $placeholders[] = ':reorder';
            $params[':reorder'] = (int) $reorderLevel;
        }

        $columnNames[] = $columns['price'];
        $placeholders[] = ':price';
        $params[':price'] = (float) $unitPrice;

        $stmt = $this->pdo->prepare(
            'INSERT INTO products (' . implode(', ', $columnNames) . ')\n'
            . 'VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProduct(int $id, array $data): bool
    {
        $columns = $this->resolveProductColumnMap();

        // Validate required fields
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Product name is required and must be 255 characters or less');
        }

        $sku = trim($data['sku'] ?? '');
        if ($columns['sku'] !== null && ($sku === '' || strlen($sku) > 100 || !preg_match('/^[A-Za-z0-9\-_]+$/', $sku))) {
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

        $category = trim((string) ($data['category'] ?? ''));
        if ($category !== '' && strlen($category) > 100) {
            throw new InvalidArgumentException('Category must be 100 characters or less');
        }

        $setParts = ['name = :name'];
        $params = [
            ':id' => $id,
            ':name' => $name,
            ':quantity' => (int) $stockQty,
            ':price' => (float) $unitPrice,
        ];

        if ($columns['sku'] !== null) {
            $setParts[] = $columns['sku'] . ' = :sku';
            $params[':sku'] = $sku;
        }

        if ($columns['category'] !== null) {
            $setParts[] = $columns['category'] . ' = :category';
            $params[':category'] = $category !== '' ? $category : null;
        }

        $setParts[] = $columns['quantity'] . ' = :quantity';

        if ($columns['reorder'] !== null) {
            $setParts[] = $columns['reorder'] . ' = :reorder';
            $params[':reorder'] = (int) $reorderLevel;
        }

        $setParts[] = $columns['price'] . ' = :price';

        $stmt = $this->pdo->prepare(
            'UPDATE products SET ' . implode(', ', $setParts) . ' WHERE id = :id'
        );
        return $stmt->execute($params);
    }

    public function deleteProduct(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getLowStockProducts(): array
    {
        $columns = $this->resolveProductColumnMap();
        $skuExpression = $columns['sku'] !== null ? $columns['sku'] : "CONCAT('SKU-', id)";
        $stockExpression = $columns['quantity'];
        $reorderExpression = $columns['reorder'] !== null ? $columns['reorder'] : '5';
        $priceExpression = $columns['price'];
        $categoryExpression = $columns['category'] !== null ? $columns['category'] : 'NULL';

        $stmt = $this->pdo->query(
            "SELECT id, name,
                    $skuExpression AS sku,
                    $categoryExpression AS category,
                    $stockExpression AS stock_qty,
                    $reorderExpression AS reorder_level,
                    $priceExpression AS unit_price
             FROM products
             WHERE $stockExpression <= $reorderExpression
             ORDER BY $stockExpression ASC"
        );
        return $stmt->fetchAll();
    }

    public function importProductsFromRows(array $rows): array
    {
        $columns = $this->resolveProductColumnMap();
        $hasSkuColumn = $columns['sku'] !== null;

        if ($hasSkuColumn) {
            $existsStmt = $this->pdo->prepare('SELECT id FROM products WHERE ' . $columns['sku'] . ' = :sku LIMIT 1');

            $insertCols = ['name', $columns['sku'], $columns['quantity'], $columns['price']];
            $insertVals = [':name', ':sku', ':quantity', ':price'];
            $updateParts = [
                'name = VALUES(name)',
                $columns['quantity'] . ' = VALUES(' . $columns['quantity'] . ')',
                $columns['price'] . ' = VALUES(' . $columns['price'] . ')',
            ];

            if ($columns['category'] !== null) {
                $insertCols[] = $columns['category'];
                $insertVals[] = ':category';
                $updateParts[] = $columns['category'] . ' = VALUES(' . $columns['category'] . ')';
            }

            if ($columns['reorder'] !== null) {
                $insertCols[] = $columns['reorder'];
                $insertVals[] = ':reorder';
                $updateParts[] = $columns['reorder'] . ' = VALUES(' . $columns['reorder'] . ')';
            }

            $upsertStmt = $this->pdo->prepare(
                'INSERT INTO products (' . implode(', ', $insertCols) . ') '
                . 'VALUES (' . implode(', ', $insertVals) . ') '
                . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts)
            );
        } else {
            $existsStmt = $this->pdo->prepare('SELECT id FROM products WHERE name = :name LIMIT 1');

            $insertCols = ['name', $columns['quantity'], $columns['price']];
            $insertVals = [':name', ':quantity', ':price'];
            if ($columns['category'] !== null) {
                $insertCols[] = $columns['category'];
                $insertVals[] = ':category';
            }
            if ($columns['reorder'] !== null) {
                $insertCols[] = $columns['reorder'];
                $insertVals[] = ':reorder';
            }

            $insertStmt = $this->pdo->prepare(
                'INSERT INTO products (' . implode(', ', $insertCols) . ') '
                . 'VALUES (' . implode(', ', $insertVals) . ')'
            );

            $updateParts = [
                $columns['quantity'] . ' = :quantity',
                $columns['price'] . ' = :price',
            ];
            if ($columns['category'] !== null) {
                $updateParts[] = $columns['category'] . ' = :category';
            }
            if ($columns['reorder'] !== null) {
                $updateParts[] = $columns['reorder'] . ' = :reorder';
            }

            $updateStmt = $this->pdo->prepare(
                'UPDATE products SET ' . implode(', ', $updateParts) . ' WHERE id = :id'
            );
        }

        $created = 0;
        $updated = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $params = [
                    ':name' => $row['name'],
                    ':quantity' => (int) $row['stock_qty'],
                    ':price' => (float) $row['unit_price'],
                ];
                if ($columns['category'] !== null) {
                    $params[':category'] = (($row['category'] ?? '') !== '') ? (string) $row['category'] : null;
                }
                if ($columns['reorder'] !== null) {
                    $params[':reorder'] = (int) $row['reorder_level'];
                }

                if ($hasSkuColumn) {
                    $params[':sku'] = (string) $row['sku'];
                    $existsStmt->execute([':sku' => (string) $row['sku']]);
                    $existing = (bool) $existsStmt->fetch();
                    $upsertStmt->execute($params);

                    if ($existing) {
                        $updated++;
                    } else {
                        $created++;
                    }
                    continue;
                }

                $existsStmt->execute([':name' => (string) $row['name']]);
                $existingRow = $existsStmt->fetch();

                if ($existingRow && isset($existingRow['id'])) {
                    $params[':id'] = (int) $existingRow['id'];
                    $updateStmt->execute($params);
                    $updated++;
                } else {
                    $insertStmt->execute($params);
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
            'sku' => isset($columns['sku']) ? 'sku' : null,
            'category' => isset($columns['category']) ? 'category' : null,
            'quantity' => isset($columns['stock_qty']) ? 'stock_qty' : 'stock',
            'reorder' => isset($columns['reorder_level']) ? 'reorder_level' : null,
            'price' => isset($columns['unit_price']) ? 'unit_price' : 'price',
        ];

        return $this->productColumnMap;
    }
}

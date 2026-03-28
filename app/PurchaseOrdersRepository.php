<?php

declare(strict_types=1);

final class PurchaseOrdersRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getOrders(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT po.id, po.po_no, po.supplier_id, s.name AS supplier_name, po.amount, po.status, po.expected_delivery_date, po.notes, po.created_at
             FROM purchase_orders po
             LEFT JOIN suppliers s ON s.id = po.supplier_id
             ORDER BY po.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM purchase_orders')->fetch()['total'];
    }

    public function getOrderItemsByOrderIds(array $orderIds): array
    {
        $normalizedIds = [];
        foreach ($orderIds as $orderId) {
            $id = (int) $orderId;
            if ($id > 0) {
                $normalizedIds[] = $id;
            }
        }

        if (count($normalizedIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT poi.purchase_order_id,
                    poi.product_id,
                    p.name AS product_name,
                    poi.quantity,
                    poi.unit_cost,
                    poi.line_total
             FROM purchase_order_items poi
             LEFT JOIN products p ON p.id = poi.product_id
             WHERE poi.purchase_order_id IN ($placeholders)
             ORDER BY poi.purchase_order_id ASC, poi.id ASC"
        );
        $stmt->execute($normalizedIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $orderId = (int) ($row['purchase_order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            if (!isset($grouped[$orderId]) || !is_array($grouped[$orderId])) {
                $grouped[$orderId] = [];
            }

            $grouped[$orderId][] = [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_cost' => (float) ($row['unit_cost'] ?? 0),
                'line_total' => (float) ($row['line_total'] ?? 0),
            ];
        }

        return $grouped;
    }

    public function getOrderSummaryById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, po_no, supplier_id, status
             FROM purchase_orders
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'po_no' => (string) ($row['po_no'] ?? ''),
            'supplier_id' => (int) ($row['supplier_id'] ?? 0),
            'status' => (string) ($row['status'] ?? 'Pending'),
        ];
    }

    public function createOrder(array $data): int
    {
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        if ($supplierId <= 0) {
            throw new InvalidArgumentException('Please select a valid supplier.');
        }

        $supplierExistsStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM suppliers WHERE id = :id');
        $supplierExistsStmt->execute([':id' => $supplierId]);
        $supplierExists = (int) ($supplierExistsStmt->fetch()['total'] ?? 0) > 0;
        if (!$supplierExists) {
            throw new InvalidArgumentException('Selected supplier does not exist.');
        }

        $rawItems = $data['items'] ?? [];
        if (!is_array($rawItems) || count($rawItems) === 0) {
            throw new InvalidArgumentException('Please add at least one purchase order item.');
        }

        $normalizedItems = [];
        $productIds = [];
        $calculatedAmount = 0.0;
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $productId = (int) ($rawItem['product_id'] ?? 0);
            $quantity = (int) ($rawItem['quantity'] ?? 0);
            $unitCost = (float) ($rawItem['unit_cost'] ?? 0);

            if ($productId <= 0) {
                throw new InvalidArgumentException('Every PO line must have a valid product.');
            }
            if ($quantity <= 0) {
                throw new InvalidArgumentException('PO quantity must be greater than zero for all items.');
            }
            if ($unitCost < 0) {
                throw new InvalidArgumentException('PO unit cost cannot be negative.');
            }

            $lineTotal = $quantity * $unitCost;
            $calculatedAmount += $lineTotal;
            $productIds[] = $productId;

            $normalizedItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }

        if (count($normalizedItems) === 0) {
            throw new InvalidArgumentException('Please add at least one purchase order item.');
        }

        if ($calculatedAmount <= 0) {
            throw new InvalidArgumentException('Purchase order total must be greater than zero.');
        }

        $enteredAmount = (float) ($data['amount'] ?? 0);
        $amount = $enteredAmount > 0 ? $enteredAmount : $calculatedAmount;

        $uniqueProductIds = array_values(array_unique($productIds));
        $productPlaceholders = implode(',', array_fill(0, count($uniqueProductIds), '?'));
        $productExistsStmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM products
             WHERE id IN ($productPlaceholders)"
        );
        $productExistsStmt->execute($uniqueProductIds);
        $existingProductsCount = (int) ($productExistsStmt->fetch()['total'] ?? 0);
        if ($existingProductsCount !== count($uniqueProductIds)) {
            throw new InvalidArgumentException('One or more selected PO products no longer exist.');
        }

        $status = trim((string) ($data['status'] ?? 'Pending'));
        $allowedStatuses = ['Pending', 'Approved', 'Received', 'Cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Pending';
        }

        $expectedDeliveryDateRaw = trim((string) ($data['expected_delivery_date'] ?? ''));
        $expectedDeliveryDate = null;
        if ($expectedDeliveryDateRaw !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $expectedDeliveryDateRaw);
            if (!$date || $date->format('Y-m-d') !== $expectedDeliveryDateRaw) {
                throw new InvalidArgumentException('Expected delivery date must be a valid date in YYYY-MM-DD format.');
            }
            $expectedDeliveryDate = $expectedDeliveryDateRaw;
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        if (strlen($notes) > 2000) {
            throw new InvalidArgumentException('PO notes cannot exceed 2000 characters.');
        }
        if ($notes === '') {
            $notes = null;
        }

        $poNo = 'PO-' . date('Ymd') . '-' . random_int(1000, 9999);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_orders (po_no, supplier_id, amount, status, expected_delivery_date, notes, created_at)
                 VALUES (:no, :supplier_id, :amount, :status, :expected_delivery_date, :notes, NOW())'
            );
            $stmt->execute([
                ':no' => $poNo,
                ':supplier_id' => $supplierId,
                ':amount' => $amount,
                ':status' => $status,
                ':expected_delivery_date' => $expectedDeliveryDate,
                ':notes' => $notes,
            ]);

            $purchaseOrderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_cost, line_total, created_at)
                 VALUES (:purchase_order_id, :product_id, :quantity, :unit_cost, :line_total, NOW())'
            );
            foreach ($normalizedItems as $item) {
                $itemStmt->execute([
                    ':purchase_order_id' => $purchaseOrderId,
                    ':product_id' => (int) ($item['product_id'] ?? 0),
                    ':quantity' => (int) ($item['quantity'] ?? 0),
                    ':unit_cost' => (float) ($item['unit_cost'] ?? 0),
                    ':line_total' => (float) ($item['line_total'] ?? 0),
                ]);
            }

            $this->pdo->commit();
            return $purchaseOrderId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateOrderStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            return false;
        }

        $normalizedStatus = trim($status);
        $allowedStatuses = ['Approved', 'Received', 'Cancelled'];
        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT status
             FROM purchase_orders
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $currentStatus = trim((string) ($row['status'] ?? 'Pending'));
        $transitions = [
            'Pending' => ['Approved', 'Cancelled'],
            'Approved' => ['Received', 'Cancelled'],
            'Received' => [],
            'Cancelled' => [],
        ];

        $allowedNext = $transitions[$currentStatus] ?? [];
        if (!in_array($normalizedStatus, $allowedNext, true)) {
            return false;
        }

        $updateStmt = $this->pdo->prepare(
            'UPDATE purchase_orders
             SET status = :status
             WHERE id = :id'
        );

        $updateStmt->execute([
            ':status' => $normalizedStatus,
            ':id' => $id,
        ]);

        return $updateStmt->rowCount() > 0;
    }
}

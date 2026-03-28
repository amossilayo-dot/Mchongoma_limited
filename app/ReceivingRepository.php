<?php

declare(strict_types=1);

final class ReceivingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getReceivings(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
                                                'SELECT r.id, r.receiving_no, r.supplier_id, r.purchase_order_id, po.po_no, s.name AS supplier_name, r.status, r.amount, r.stock_applied, r.created_at
             FROM receiving r
             LEFT JOIN suppliers s ON s.id = r.supplier_id
                         LEFT JOIN purchase_orders po ON po.id = r.purchase_order_id
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM receiving')->fetch()['total'];
    }

    public function getReceivingItemsByReceivingIds(array $receivingIds): array
    {
        $normalizedIds = array_values(array_filter(array_map(
            static fn($id): int => (int) $id,
            $receivingIds
        ), static fn(int $id): bool => $id > 0));

        if ($normalizedIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT ri.receiving_id, ri.product_id, p.name AS product_name,
                    ri.quantity_received, ri.quantity_rejected, ri.unit_cost, ri.line_total
             FROM receiving_items ri
             INNER JOIN products p ON p.id = ri.product_id
             WHERE ri.receiving_id IN ($placeholders)
             ORDER BY ri.id ASC"
        );
        $stmt->execute($normalizedIds);

        $rows = $stmt->fetchAll() ?: [];
        $result = [];
        foreach ($rows as $row) {
            $receivingId = (int) ($row['receiving_id'] ?? 0);
            if ($receivingId <= 0) {
                continue;
            }

            if (!isset($result[$receivingId]) || !is_array($result[$receivingId])) {
                $result[$receivingId] = [];
            }

            $result[$receivingId][] = [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'quantity_received' => (int) ($row['quantity_received'] ?? 0),
                'quantity_rejected' => (int) ($row['quantity_rejected'] ?? 0),
                'unit_cost' => (float) ($row['unit_cost'] ?? 0),
                'line_total' => (float) ($row['line_total'] ?? 0),
            ];
        }

        return $result;
    }

    public function createReceiving(array $data): int
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

        $purchaseOrderId = (int) ($data['purchase_order_id'] ?? 0);
        if ($purchaseOrderId > 0) {
            $purchaseOrderStmt = $this->pdo->prepare(
                'SELECT supplier_id, status
                 FROM purchase_orders
                 WHERE id = :id
                 LIMIT 1'
            );
            $purchaseOrderStmt->execute([':id' => $purchaseOrderId]);
            $purchaseOrder = $purchaseOrderStmt->fetch();

            if (!$purchaseOrder) {
                throw new InvalidArgumentException('Selected purchase order does not exist.');
            }

            $poSupplierId = (int) ($purchaseOrder['supplier_id'] ?? 0);
            if ($poSupplierId !== $supplierId) {
                throw new InvalidArgumentException('Selected purchase order belongs to a different supplier.');
            }

            $poStatus = trim((string) ($purchaseOrder['status'] ?? 'Pending'));
            if (in_array($poStatus, ['Received', 'Cancelled'], true)) {
                throw new InvalidArgumentException('Selected purchase order is already closed.');
            }
        }

        $status = trim((string) ($data['status'] ?? 'Pending'));
        $allowedStatuses = ['Pending', 'Received', 'Completed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Pending';
        }

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $normalizedItems = [];
        $calculatedAmount = 0.0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $qtyReceived = max(0, (int) ($item['quantity_received'] ?? 0));
            $qtyRejected = max(0, (int) ($item['quantity_rejected'] ?? 0));
            $unitCost = max(0.0, (float) ($item['unit_cost'] ?? 0));

            if ($productId <= 0 || ($qtyReceived + $qtyRejected) <= 0) {
                continue;
            }

            $lineTotal = $qtyReceived * $unitCost;
            $calculatedAmount += $lineTotal;

            $normalizedItems[] = [
                'product_id' => $productId,
                'quantity_received' => $qtyReceived,
                'quantity_rejected' => $qtyRejected,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }

        if ($normalizedItems === []) {
            throw new InvalidArgumentException('At least one receiving item is required.');
        }

        $enteredAmount = (float) ($data['amount'] ?? 0);
        $amount = $enteredAmount > 0 ? $enteredAmount : $calculatedAmount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $productExistsStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM products WHERE id = :id');
        foreach ($normalizedItems as $normalizedItem) {
            $productExistsStmt->execute([':id' => $normalizedItem['product_id']]);
            $productExists = (int) ($productExistsStmt->fetch()['total'] ?? 0) > 0;
            if (!$productExists) {
                throw new InvalidArgumentException('One or more selected products do not exist.');
            }
        }

        $receivingNo = 'RCV-' . date('Ymd') . '-' . random_int(1000, 9999);
        $shouldApplyStock = $status === 'Completed';

        $this->pdo->beginTransaction();
        try {
            $receivingStmt = $this->pdo->prepare(
                'INSERT INTO receiving (receiving_no, supplier_id, purchase_order_id, status, amount, stock_applied, created_at) VALUES (:no, :supplier_id, :purchase_order_id, :status, :amount, :stock_applied, NOW())'
            );
            $receivingStmt->execute([
                ':no' => $receivingNo,
                ':supplier_id' => $supplierId,
                ':purchase_order_id' => $purchaseOrderId > 0 ? $purchaseOrderId : null,
                ':status' => $status,
                ':amount' => $amount,
                ':stock_applied' => $shouldApplyStock ? 1 : 0,
            ]);

            $receivingId = (int) $this->pdo->lastInsertId();

            $itemInsertStmt = $this->pdo->prepare(
                'INSERT INTO receiving_items (receiving_id, product_id, quantity_received, quantity_rejected, unit_cost, line_total, created_at)
                 VALUES (:receiving_id, :product_id, :qty_received, :qty_rejected, :unit_cost, :line_total, NOW())'
            );
            $stockUpdateStmt = $this->pdo->prepare(
                'UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :product_id'
            );

            foreach ($normalizedItems as $normalizedItem) {
                $itemInsertStmt->execute([
                    ':receiving_id' => $receivingId,
                    ':product_id' => $normalizedItem['product_id'],
                    ':qty_received' => $normalizedItem['quantity_received'],
                    ':qty_rejected' => $normalizedItem['quantity_rejected'],
                    ':unit_cost' => $normalizedItem['unit_cost'],
                    ':line_total' => $normalizedItem['line_total'],
                ]);

                if ($shouldApplyStock && $normalizedItem['quantity_received'] > 0) {
                    $stockUpdateStmt->execute([
                        ':qty' => $normalizedItem['quantity_received'],
                        ':product_id' => $normalizedItem['product_id'],
                    ]);
                }
            }

            if ($shouldApplyStock && $purchaseOrderId > 0) {
                $this->syncLinkedPurchaseOrderStatus($purchaseOrderId);
            }

            $this->pdo->commit();
            return $receivingId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateReceivingStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            return false;
        }

        $normalizedStatus = trim($status);
        $allowedStatuses = ['Pending', 'Received', 'Completed'];
        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $receivingStmt = $this->pdo->prepare(
                'SELECT id, status, stock_applied, purchase_order_id
                 FROM receiving
                 WHERE id = :id
                 FOR UPDATE'
            );
            $receivingStmt->execute([':id' => $id]);
            $receiving = $receivingStmt->fetch();
            if (!$receiving) {
                $this->pdo->rollBack();
                return false;
            }

            $currentStatus = trim((string) ($receiving['status'] ?? 'Pending'));
            if (strtolower($currentStatus) === 'completed' && $normalizedStatus !== 'Completed') {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('Completed receiving cannot be moved back to Pending or Received.');
            }

            $stockApplied = ((int) ($receiving['stock_applied'] ?? 0)) === 1;
            if ($normalizedStatus === 'Completed' && !$stockApplied) {
                $itemsStmt = $this->pdo->prepare(
                    'SELECT product_id, quantity_received
                     FROM receiving_items
                     WHERE receiving_id = :receiving_id'
                );
                $itemsStmt->execute([':receiving_id' => $id]);
                $items = $itemsStmt->fetchAll() ?: [];
                if ($items === []) {
                    $this->pdo->rollBack();
                    return false;
                }

                $stockUpdateStmt = $this->pdo->prepare(
                    'UPDATE products
                     SET stock_qty = stock_qty + :qty
                     WHERE id = :product_id'
                );
                foreach ($items as $item) {
                    $qtyReceived = max(0, (int) ($item['quantity_received'] ?? 0));
                    $productId = (int) ($item['product_id'] ?? 0);
                    if ($qtyReceived <= 0 || $productId <= 0) {
                        continue;
                    }

                    $stockUpdateStmt->execute([
                        ':qty' => $qtyReceived,
                        ':product_id' => $productId,
                    ]);
                }

                $stockApplied = true;
            }

            $updateStmt = $this->pdo->prepare(
                'UPDATE receiving
                 SET status = :status,
                     stock_applied = :stock_applied
                 WHERE id = :id'
            );
            $updated = $updateStmt->execute([
                ':status' => $normalizedStatus,
                ':stock_applied' => $stockApplied ? 1 : 0,
                ':id' => $id,
            ]);

            $purchaseOrderId = (int) ($receiving['purchase_order_id'] ?? 0);
            if ($updated && $normalizedStatus === 'Completed' && $purchaseOrderId > 0) {
                $this->syncLinkedPurchaseOrderStatus($purchaseOrderId);
            }

            $this->pdo->commit();
            return $updated;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function syncLinkedPurchaseOrderStatus(int $purchaseOrderId): void
    {
        if ($purchaseOrderId <= 0) {
            return;
        }

        $poStmt = $this->pdo->prepare(
            'SELECT status
             FROM purchase_orders
             WHERE id = :id
             FOR UPDATE'
        );
        $poStmt->execute([':id' => $purchaseOrderId]);
        $purchaseOrder = $poStmt->fetch();
        if (!$purchaseOrder) {
            return;
        }

        $currentStatus = trim((string) ($purchaseOrder['status'] ?? 'Pending'));
        if ($currentStatus === 'Cancelled' || $currentStatus === 'Received') {
            return;
        }

        if ($currentStatus === 'Pending') {
            $approveStmt = $this->pdo->prepare(
                'UPDATE purchase_orders
                 SET status = "Approved"
                 WHERE id = :id AND status = "Pending"'
            );
            $approveStmt->execute([':id' => $purchaseOrderId]);
        }

        $receiveStmt = $this->pdo->prepare(
            'UPDATE purchase_orders
             SET status = "Received"
             WHERE id = :id AND status IN ("Approved", "Pending")'
        );
        $receiveStmt->execute([':id' => $purchaseOrderId]);
    }
}

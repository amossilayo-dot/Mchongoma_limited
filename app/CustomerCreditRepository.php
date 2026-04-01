<?php

declare(strict_types=1);

final class CustomerCreditRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createCreditForSale(int $saleId, int $customerId, float $amount, string $notes = '', ?string $dueDate = null): int
    {
        if ($saleId <= 0 || $customerId <= 0) {
            throw new InvalidArgumentException('Valid sale and customer are required for credit creation.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than zero.');
        }

        $normalizedDueDate = null;
        if ($dueDate !== null && trim($dueDate) !== '') {
            $parsedDueDate = DateTimeImmutable::createFromFormat('Y-m-d', trim($dueDate));
            if (!$parsedDueDate) {
                throw new InvalidArgumentException('Invalid credit due date format.');
            }
            $normalizedDueDate = $parsedDueDate->format('Y-m-d');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO customer_credits (
                sale_id,
                customer_id,
                total_amount,
                paid_amount,
                outstanding_amount,
                status,
                due_date,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :sale_id,
                :customer_id,
                :total_amount,
                0,
                :outstanding_amount,
                :status,
                :due_date,
                :notes,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            ':sale_id' => $saleId,
            ':customer_id' => $customerId,
            ':total_amount' => $amount,
            ':outstanding_amount' => $amount,
            ':status' => 'Open',
            ':due_date' => $normalizedDueDate,
            ':notes' => $notes !== '' ? $notes : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getCredits(int $limit = 200, bool $openOnly = false): array
    {
        $sql = 'SELECT cc.id, cc.sale_id, cc.customer_id, cc.total_amount, cc.paid_amount,
                   cc.outstanding_amount, cc.status, cc.due_date, cc.notes, cc.created_at, cc.updated_at,
                       c.name AS customer_name,
                       s.transaction_no
                FROM customer_credits cc
                JOIN customers c ON c.id = cc.customer_id
                LEFT JOIN sales s ON s.id = cc.sale_id
                WHERE cc.deleted_at IS NULL';

        if ($openOnly) {
            $sql .= " AND cc.outstanding_amount > 0 AND cc.status <> 'Paid'";
        }

        $sql .= ' ORDER BY cc.created_at DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getCreditsPage(int $limit = 200, int $offset = 0, string $query = ''): array
    {
        $limit = max(1, min($limit, 2000));
        $offset = max(0, $offset);
        $normalized = trim($query);
        $isNumericSearch = $normalized !== '' && ctype_digit($normalized);

        $sql = 'SELECT cc.id, cc.sale_id, cc.customer_id, cc.total_amount, cc.paid_amount,
                       cc.outstanding_amount, cc.status, cc.due_date, cc.notes, cc.created_at, cc.updated_at,
                       c.name AS customer_name,
                       s.transaction_no
                FROM customer_credits cc
                JOIN customers c ON c.id = cc.customer_id
                LEFT JOIN sales s ON s.id = cc.sale_id
                WHERE cc.deleted_at IS NULL';

        if ($normalized !== '') {
            $sql .= ' AND (
                        c.name LIKE :search
                        OR COALESCE(c.phone, "") LIKE :search
                        OR COALESCE(s.transaction_no, "") LIKE :search
                        OR CAST(cc.sale_id AS CHAR) LIKE :search
                        OR CAST(cc.id AS CHAR) LIKE :search
                        OR DATE_FORMAT(cc.created_at, "%Y-%m-%d") LIKE :search';
            if ($isNumericSearch) {
                $sql .= ' OR cc.customer_id = :numeric_id';
            }
            $sql .= ')';
        }

        $sql .= ' ORDER BY cc.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        if ($normalized !== '') {
            $stmt->bindValue(':search', '%' . $normalized . '%', PDO::PARAM_STR);
            if ($isNumericSearch) {
                $stmt->bindValue(':numeric_id', (int) $normalized, PDO::PARAM_INT);
            }
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countCredits(string $query = ''): int
    {
        $normalized = trim($query);
        $isNumericSearch = $normalized !== '' && ctype_digit($normalized);

        $sql = 'SELECT COUNT(*) AS total
                FROM customer_credits cc
                JOIN customers c ON c.id = cc.customer_id
                LEFT JOIN sales s ON s.id = cc.sale_id
                WHERE cc.deleted_at IS NULL';

        if ($normalized !== '') {
            $sql .= ' AND (
                        c.name LIKE :search
                        OR COALESCE(c.phone, "") LIKE :search
                        OR COALESCE(s.transaction_no, "") LIKE :search
                        OR CAST(cc.sale_id AS CHAR) LIKE :search
                        OR CAST(cc.id AS CHAR) LIKE :search
                        OR DATE_FORMAT(cc.created_at, "%Y-%m-%d") LIKE :search';
            if ($isNumericSearch) {
                $sql .= ' OR cc.customer_id = :numeric_id';
            }
            $sql .= ')';
        }

        $stmt = $this->pdo->prepare($sql);
        if ($normalized !== '') {
            $stmt->bindValue(':search', '%' . $normalized . '%', PDO::PARAM_STR);
            if ($isNumericSearch) {
                $stmt->bindValue(':numeric_id', (int) $normalized, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function searchCredits(string $query, int $limit = 500): array
    {
        $normalized = trim($query);
        if ($normalized === '') {
            return $this->getCredits($limit, false);
        }

        return $this->getCreditsPage($limit, 0, $normalized);
    }

    public function getCustomerCredits(int $customerId, int $limit = 200, bool $openOnly = false): array
    {
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Valid customer ID is required.');
        }

        $sql = 'SELECT cc.id, cc.sale_id, cc.customer_id, cc.total_amount, cc.paid_amount,
                       cc.outstanding_amount, cc.status, cc.due_date, cc.notes, cc.created_at, cc.updated_at,
                       c.name AS customer_name,
                       s.transaction_no
                FROM customer_credits cc
                JOIN customers c ON c.id = cc.customer_id
                LEFT JOIN sales s ON s.id = cc.sale_id
                                WHERE cc.customer_id = :customer_id
                                    AND cc.deleted_at IS NULL';

        if ($openOnly) {
            $sql .= ' AND cc.outstanding_amount > 0 AND cc.status <> "Paid"';
        }

        $sql .= ' ORDER BY cc.created_at DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getCustomerPayments(int $customerId, int $limit = 500): array
    {
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Valid customer ID is required.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.credit_id, p.customer_id, p.amount, p.payment_method, p.reference, p.created_at,
                    cc.sale_id,
                    s.transaction_no
             FROM customer_credit_payments p
             JOIN customer_credits cc ON cc.id = p.credit_id
             LEFT JOIN sales s ON s.id = cc.sale_id
             WHERE p.customer_id = :customer_id
             ORDER BY p.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getCustomerOutstandingTotals(): array
    {
        $stmt = $this->pdo->query(
            'SELECT customer_id, COALESCE(SUM(outstanding_amount), 0) AS outstanding_total
             FROM customer_credits
                         WHERE deleted_at IS NULL
                             AND outstanding_amount > 0
                             AND status <> "Paid"
             GROUP BY customer_id'
        );

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['customer_id']] = (float) $row['outstanding_total'];
        }

        return $map;
    }

    public function getPaymentsByCreditIds(array $creditIds, int $limit = 1000): array
    {
        $normalizedIds = [];
        foreach ($creditIds as $creditId) {
            $id = (int) $creditId;
            if ($id > 0) {
                $normalizedIds[$id] = true;
            }
        }

        $ids = array_keys($normalizedIds);
        if (count($ids) === 0) {
            return [];
        }

        $limit = max(1, min($limit, 5000));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT p.id, p.credit_id, p.customer_id, p.amount, p.payment_method, p.reference, p.created_at,
                       cc.sale_id,
                       s.transaction_no
                FROM customer_credit_payments p
                JOIN customer_credits cc ON cc.id = p.credit_id
                LEFT JOIN sales s ON s.id = cc.sale_id
                WHERE p.credit_id IN (' . $placeholders . ')
                ORDER BY p.created_at DESC
                LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getSaleItemsBySaleIds(array $saleIds, int $limit = 3000): array
    {
        $normalizedIds = [];
        foreach ($saleIds as $saleId) {
            $id = (int) $saleId;
            if ($id > 0) {
                $normalizedIds[$id] = true;
            }
        }

        $ids = array_keys($normalizedIds);
        if (count($ids) === 0) {
            return [];
        }

        $limit = max(1, min($limit, 10000));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT si.id, si.sale_id, si.product_id, si.quantity, si.unit_price, si.line_total,
                   si.note, si.created_at, p.name AS product_name
                FROM sale_items si
                LEFT JOIN products p ON p.id = si.product_id
                WHERE si.sale_id IN (' . $placeholders . ')
                ORDER BY si.sale_id DESC, si.id ASC
                LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function recordPayment(int $creditId, float $amount, string $paymentMethod, string $reference = ''): array
    {
        if ($creditId <= 0) {
            throw new InvalidArgumentException('Valid credit ID is required.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $allowedPaymentMethods = ['Cash', 'Mobile Money', 'Card', 'Bank Transfer'];
        if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
            throw new InvalidArgumentException('Payment method is invalid.');
        }

        $this->pdo->beginTransaction();
        try {
            $creditStmt = $this->pdo->prepare(
                'SELECT id, customer_id, total_amount, paid_amount, outstanding_amount
                 FROM customer_credits
                                 WHERE id = :id
                                     AND deleted_at IS NULL
                 FOR UPDATE'
            );
            $creditStmt->execute([':id' => $creditId]);
            $credit = $creditStmt->fetch();

            if (!$credit) {
                throw new RuntimeException('Credit record not found.');
            }

            $outstanding = (float) $credit['outstanding_amount'];
            if ($outstanding <= 0) {
                throw new RuntimeException('This credit is already fully paid.');
            }

            if ($amount > $outstanding) {
                throw new RuntimeException('Payment amount cannot exceed outstanding balance.');
            }

            $paymentStmt = $this->pdo->prepare(
                'INSERT INTO customer_credit_payments (credit_id, customer_id, amount, payment_method, reference, created_at)
                 VALUES (:credit_id, :customer_id, :amount, :payment_method, :reference, NOW())'
            );
            $paymentStmt->execute([
                ':credit_id' => $creditId,
                ':customer_id' => (int) $credit['customer_id'],
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':reference' => $reference !== '' ? $reference : null,
            ]);

            $newPaid = (float) $credit['paid_amount'] + $amount;
            $newOutstanding = max(0.0, (float) $credit['total_amount'] - $newPaid);
            $status = $newOutstanding <= 0 ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Open');

            $updateStmt = $this->pdo->prepare(
                'UPDATE customer_credits
                 SET paid_amount = :paid_amount,
                     outstanding_amount = :outstanding_amount,
                     status = :status,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':id' => $creditId,
                ':paid_amount' => $newPaid,
                ':outstanding_amount' => $newOutstanding,
                ':status' => $status,
            ]);

            $this->pdo->commit();

            return [
                'credit_id' => $creditId,
                'new_paid_amount' => $newPaid,
                'new_outstanding_amount' => $newOutstanding,
                'status' => $status,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function softDeleteCredit(int $creditId): bool
    {
        if ($creditId <= 0) {
            throw new InvalidArgumentException('Valid credit ID is required.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE customer_credits
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $creditId]);

        return $stmt->rowCount() > 0;
    }

    public function restoreCredit(int $creditId): bool
    {
        if ($creditId <= 0) {
            throw new InvalidArgumentException('Valid credit ID is required.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE customer_credits
             SET deleted_at = NULL,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NOT NULL'
        );
        $stmt->execute([':id' => $creditId]);

        return $stmt->rowCount() > 0;
    }

    public function getDeletedCredits(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $stmt = $this->pdo->prepare(
            'SELECT cc.id, cc.sale_id, cc.customer_id, cc.total_amount, cc.paid_amount,
                    cc.outstanding_amount, cc.status, cc.due_date, cc.notes, cc.created_at, cc.updated_at,
                    cc.deleted_at,
                    c.name AS customer_name,
                    s.transaction_no
             FROM customer_credits cc
             JOIN customers c ON c.id = cc.customer_id
             LEFT JOIN sales s ON s.id = cc.sale_id
             WHERE cc.deleted_at IS NOT NULL
             ORDER BY cc.deleted_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

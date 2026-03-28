<?php

declare(strict_types=1);

final class QuotationsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getQuotations(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.id,
                    q.quotation_no,
                    q.customer_id,
                    c.name AS customer_name,
                    q.amount,
                    q.status,
                    q.created_at
             FROM quotations q
             LEFT JOIN customers c ON c.id = q.customer_id
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM quotations')->fetch()['total'];
    }

    public function createQuotation(array $data): int
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please select a valid customer.');
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Quotation amount must be greater than zero.');
        }

        $customerExistsStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM customers WHERE id = :id');
        $customerExistsStmt->execute([':id' => $customerId]);
        $customerExists = (int) ($customerExistsStmt->fetch()['total'] ?? 0) > 0;
        if (!$customerExists) {
            throw new InvalidArgumentException('Selected customer does not exist.');
        }

        $status = trim((string) ($data['status'] ?? 'Pending'));
        $allowedStatuses = ['Pending', 'Approved', 'Rejected', 'Expired'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Pending';
        }

        $quotationNo = 'QT-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $this->pdo->prepare(
            'INSERT INTO quotations (quotation_no, customer_id, amount, status, created_at) VALUES (:no, :customer_id, :amount, :status, NOW())'
        );
        $stmt->execute([
            ':no' => $quotationNo,
            ':customer_id' => $customerId,
            ':amount' => $amount,
            ':status' => $status,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateQuotationStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            return false;
        }

        $requestedStatusRaw = strtolower(trim($status));
        $statusMap = [
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
        ];
        $normalizedStatus = $statusMap[$requestedStatusRaw] ?? null;
        if ($normalizedStatus === null) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT status
             FROM quotations
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $currentStatusRaw = strtolower(trim((string) ($row['status'] ?? 'pending')));
        if ($currentStatusRaw === '') {
            $currentStatusRaw = 'pending';
        }

        $currentStatusMap = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
        ];
        $currentStatus = $currentStatusMap[$currentStatusRaw] ?? null;
        if ($currentStatus === null) {
            return false;
        }

        $transitions = [
            'Pending' => ['Approved', 'Rejected', 'Expired'],
            'Approved' => ['Expired'],
            'Rejected' => [],
            'Expired' => [],
        ];

        $allowedNext = $transitions[$currentStatus] ?? [];
        if (!in_array($normalizedStatus, $allowedNext, true)) {
            return false;
        }

        $updateStmt = $this->pdo->prepare(
            'UPDATE quotations
             SET status = :status
             WHERE id = :id'
        );

        $updateStmt->execute([
            ':status' => $normalizedStatus,
            ':id' => $id,
        ]);

        return $updateStmt->rowCount() > 0;
    }

    public function updateQuotation(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid quotation ID.');
        }

        $customerId = (int) ($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please select a valid customer.');
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Quotation amount must be greater than zero.');
        }

        $quotationStmt = $this->pdo->prepare(
            'SELECT status
             FROM quotations
             WHERE id = :id
             LIMIT 1'
        );
        $quotationStmt->execute([':id' => $id]);
        $quotationRow = $quotationStmt->fetch();
        if (!$quotationRow) {
            throw new InvalidArgumentException('Quotation not found.');
        }

        $currentStatus = trim((string) ($quotationRow['status'] ?? 'Pending'));
        if ($currentStatus === '') {
            $currentStatus = 'Pending';
        }
        if (strtolower($currentStatus) !== 'pending') {
            throw new InvalidArgumentException('Only pending quotations can be edited.');
        }

        $customerExistsStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM customers WHERE id = :id');
        $customerExistsStmt->execute([':id' => $customerId]);
        $customerExists = (int) ($customerExistsStmt->fetch()['total'] ?? 0) > 0;
        if (!$customerExists) {
            throw new InvalidArgumentException('Selected customer does not exist.');
        }

        $updateStmt = $this->pdo->prepare(
            'UPDATE quotations
             SET customer_id = :customer_id,
                 amount = :amount
             WHERE id = :id'
        );

        $updateStmt->execute([
            ':customer_id' => $customerId,
            ':amount' => $amount,
            ':id' => $id,
        ]);

        return $updateStmt->rowCount() > 0;
    }

    public function deleteQuotation(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $statusStmt = $this->pdo->prepare(
            'SELECT status
             FROM quotations
             WHERE id = :id
             LIMIT 1'
        );
        $statusStmt->execute([':id' => $id]);
        $row = $statusStmt->fetch();
        if (!$row) {
            return false;
        }

        $currentStatus = strtolower(trim((string) ($row['status'] ?? 'pending')));
        if ($currentStatus === '') {
            $currentStatus = 'pending';
        }

        if ($currentStatus === 'approved') {
            throw new InvalidArgumentException('Approved quotations cannot be deleted.');
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM quotations
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}

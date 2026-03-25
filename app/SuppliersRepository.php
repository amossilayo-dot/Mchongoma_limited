<?php

declare(strict_types=1);

final class SuppliersRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getSuppliers(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, contact_person, phone, email, address, status, created_at
             FROM suppliers
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM suppliers')->fetch()['total'];
    }

    public function getSupplier(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suppliers WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createSupplier(array $data): int
    {
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Supplier name is required and must be 255 characters or less');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO suppliers (name, contact_person, phone, email, address, status) VALUES (:name, :contact, :phone, :email, :address, :status)'
        );
        $stmt->execute([
            ':name' => $name,
            ':contact' => trim($data['contact_person'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':email' => trim($data['email'] ?? ''),
            ':address' => trim($data['address'] ?? ''),
            ':status' => $data['status'] ?? 'Active',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateSupplier(int $id, array $data): bool
    {
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Supplier name is required and must be 255 characters or less');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE suppliers SET name = :name, contact_person = :contact, phone = :phone, email = :email, address = :address, status = :status WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':contact' => trim($data['contact_person'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':email' => trim($data['email'] ?? ''),
            ':address' => trim($data['address'] ?? ''),
            ':status' => $data['status'] ?? 'Active',
        ]);
    }

    public function deleteSupplier(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM suppliers WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}

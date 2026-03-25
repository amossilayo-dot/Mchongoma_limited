<?php

declare(strict_types=1);

final class LocationsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getLocations(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, address, city, phone, status, created_at
             FROM locations
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM locations')->fetch()['total'];
    }

    public function createLocation(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO locations (name, address, city, phone, status) VALUES (:name, :address, :city, :phone, :status)'
        );
        $stmt->execute([
            ':name' => trim($data['name'] ?? ''),
            ':address' => trim($data['address'] ?? ''),
            ':city' => trim($data['city'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':status' => $data['status'] ?? 'Active',
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

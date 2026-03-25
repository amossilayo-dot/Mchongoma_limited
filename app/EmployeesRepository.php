<?php

declare(strict_types=1);

final class EmployeesRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getEmployees(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, position, phone, email, salary, status, created_at
             FROM employees
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM employees')->fetch()['total'];
    }

    public function getEmployee(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM employees WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function createEmployee(array $data): int
    {
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Employee name is required');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO employees (name, position, phone, email, salary, status) VALUES (:name, :position, :phone, :email, :salary, :status)'
        );
        $stmt->execute([
            ':name' => $name,
            ':position' => trim($data['position'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':email' => trim($data['email'] ?? ''),
            ':salary' => isset($data['salary']) ? (float)$data['salary'] : 0,
            ':status' => $data['status'] ?? 'Active',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateEmployee(int $id, array $data): bool
    {
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Employee name is required');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE employees SET name = :name, position = :position, phone = :phone, email = :email, salary = :salary, status = :status WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':position' => trim($data['position'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':email' => trim($data['email'] ?? ''),
            ':salary' => isset($data['salary']) ? (float)$data['salary'] : 0,
            ':status' => $data['status'] ?? 'Active',
        ]);
    }

    public function deleteEmployee(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM employees WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}

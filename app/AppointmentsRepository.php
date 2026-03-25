<?php

declare(strict_types=1);

final class AppointmentsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getAppointments(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, customer_id, appointment_date, status, created_at
             FROM appointments
             ORDER BY appointment_date DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM appointments')->fetch()['total'];
    }

    public function createAppointment(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO appointments (title, customer_id, appointment_date, status, created_at) VALUES (:title, :customer_id, :date, :status, NOW())'
        );
        $stmt->execute([
            ':title' => trim($data['title'] ?? ''),
            ':customer_id' => (int)$data['customer_id'],
            ':date' => $data['appointment_date'],
            ':status' => $data['status'] ?? 'Scheduled',
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

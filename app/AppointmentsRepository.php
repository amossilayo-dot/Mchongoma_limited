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
            'SELECT a.id, a.title, a.customer_id, c.name AS customer_name, a.appointment_date, a.status, a.created_at
             FROM appointments a
             LEFT JOIN customers c ON c.id = a.customer_id
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
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || strlen($title) > 255) {
            throw new InvalidArgumentException('Appointment title is required and must be 255 characters or less.');
        }

        $customerId = (int) ($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please select a valid customer.');
        }

        $rawDate = trim((string) ($data['appointment_date'] ?? ''));
        if ($rawDate === '') {
            throw new InvalidArgumentException('Appointment date and time are required.');
        }

        $normalizedDate = str_replace('T', ' ', $rawDate);
        $appointmentDate = DateTimeImmutable::createFromFormat('Y-m-d H:i', $normalizedDate)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalizedDate);
        if (!$appointmentDate) {
            throw new InvalidArgumentException('Invalid appointment date format.');
        }

        $status = trim((string) ($data['status'] ?? 'Scheduled'));
        $allowedStatuses = ['Scheduled', 'Completed', 'Cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Scheduled';
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO appointments (title, customer_id, appointment_date, status, created_at) VALUES (:title, :customer_id, :date, :status, NOW())'
        );
        $stmt->execute([
            ':title' => $title,
            ':customer_id' => $customerId,
            ':date' => $appointmentDate->format('Y-m-d H:i:s'),
            ':status' => $status,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteAppointment(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM appointments WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function updateAppointmentStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            return false;
        }

        $normalized = trim($status);
        $allowedStatuses = ['Scheduled', 'Completed', 'Cancelled'];
        if (!in_array($normalized, $allowedStatuses, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE appointments SET status = :status WHERE id = :id');
        return $stmt->execute([
            ':status' => $normalized,
            ':id' => $id,
        ]);
    }
}

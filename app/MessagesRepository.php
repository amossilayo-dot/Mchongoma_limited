<?php

declare(strict_types=1);

final class MessagesRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getMessages(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sender, recipient, subject, message, is_read, created_at
             FROM messages
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
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM messages')->fetch()['total'];
    }

    public function getUnreadCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM messages WHERE is_read = 0')->fetch()['total'];
    }

    public function createMessage(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (sender, recipient, subject, message, created_at) VALUES (:sender, :recipient, :subject, :msg, NOW())'
        );
        $stmt->execute([
            ':sender' => trim($data['sender'] ?? ''),
            ':recipient' => trim($data['recipient'] ?? ''),
            ':subject' => trim($data['subject'] ?? ''),
            ':msg' => trim($data['message'] ?? ''),
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}

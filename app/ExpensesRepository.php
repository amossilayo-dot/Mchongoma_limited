<?php

declare(strict_types=1);

final class ExpensesRepository
{
    /** @var array<string, true>|null */
    private ?array $expenseColumns = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function getExpenses(int $limit = 50, int $offset = 0): array
    {
        $descriptionField = $this->hasExpenseColumn('description') ? 'description' : 'title';
        $statusSelect = $this->hasExpenseColumn('status') ? 'status' : '"Approved" AS status';
        $createdField = $this->hasExpenseColumn('created_at') ? 'created_at' : 'expense_date';

        $stmt = $this->pdo->prepare(
            'SELECT id, ' . $descriptionField . ' AS description, category, amount, ' . $statusSelect . ', ' . $createdField . ' AS created_at
             FROM expenses
             ORDER BY ' . $createdField . ' DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS total FROM expenses')->fetch()['total'];
    }

    public function createExpense(array $data): int
    {
        $description = trim((string) ($data['description'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);
        $status = trim((string) ($data['status'] ?? 'Pending'));

        if ($description === '') {
            throw new RuntimeException('Expense description is required.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Expense amount must be greater than zero.');
        }

        $fields = [];
        $values = [];
        $params = [];

        if ($this->hasExpenseColumn('description')) {
            $fields[] = 'description';
            $values[] = ':description';
            $params[':description'] = $description;
        } else {
            $fields[] = 'title';
            $values[] = ':title';
            $params[':title'] = $description;
        }

        if ($this->hasExpenseColumn('category')) {
            $fields[] = 'category';
            $values[] = ':category';
            $params[':category'] = $category !== '' ? $category : 'General';
        }

        $fields[] = 'amount';
        $values[] = ':amount';
        $params[':amount'] = $amount;

        if ($this->hasExpenseColumn('status')) {
            $fields[] = 'status';
            $values[] = ':status';
            $params[':status'] = $status !== '' ? $status : 'Pending';
        }

        if ($this->hasExpenseColumn('warehouse_id')) {
            $fields[] = 'warehouse_id';
            $values[] = ':warehouse_id';
            $params[':warehouse_id'] = $this->resolveWarehouseId();
        }

        if ($this->hasExpenseColumn('created_at')) {
            $fields[] = 'created_at';
            $values[] = 'NOW()';
        } elseif ($this->hasExpenseColumn('expense_date')) {
            $fields[] = 'expense_date';
            $values[] = 'NOW()';
        }

        $sql = 'INSERT INTO expenses (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateExpense(int $id, array $data): bool
    {
        $description = trim((string) ($data['description'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);
        $status = trim((string) ($data['status'] ?? 'Pending'));

        $sets = [];
        $params = [':id' => $id];

        if ($this->hasExpenseColumn('description')) {
            $sets[] = 'description = :description';
            $params[':description'] = $description;
        } elseif ($this->hasExpenseColumn('title')) {
            $sets[] = 'title = :title';
            $params[':title'] = $description;
        }

        if ($this->hasExpenseColumn('category')) {
            $sets[] = 'category = :category';
            $params[':category'] = $category;
        }

        $sets[] = 'amount = :amount';
        $params[':amount'] = $amount;

        if ($this->hasExpenseColumn('status')) {
            $sets[] = 'status = :status';
            $params[':status'] = $status !== '' ? $status : 'Pending';
        }

        if ($sets === []) {
            throw new RuntimeException('Expenses table does not contain editable columns.');
        }

        $stmt = $this->pdo->prepare('UPDATE expenses SET ' . implode(', ', $sets) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public function deleteExpense(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM expenses WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getTotalByCategory(): array
    {
        $sql = 'SELECT category, SUM(amount) AS total FROM expenses';
        if ($this->hasExpenseColumn('status')) {
            $sql .= ' WHERE status = "Approved"';
        }
        $sql .= ' GROUP BY category';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /** @return array<string, true> */
    private function getExpenseColumns(): array
    {
        if ($this->expenseColumns !== null) {
            return $this->expenseColumns;
        }

        $columns = [];
        $stmt = $this->pdo->query('SHOW COLUMNS FROM expenses');
        foreach ($stmt->fetchAll() as $column) {
            $field = (string) ($column['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = true;
            }
        }

        $this->expenseColumns = $columns;
        return $this->expenseColumns;
    }

    private function hasExpenseColumn(string $name): bool
    {
        $columns = $this->getExpenseColumns();
        return isset($columns[$name]);
    }

    private function resolveWarehouseId(): int
    {
        $stmt = $this->pdo->query('SELECT id FROM warehouses ORDER BY id ASC LIMIT 1');
        $warehouse = $stmt->fetch();
        if (!$warehouse || !isset($warehouse['id'])) {
            throw new RuntimeException('Please add a warehouse before saving expenses.');
        }

        return (int) $warehouse['id'];
    }
}

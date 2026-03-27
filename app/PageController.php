<?php

declare(strict_types=1);

final class PageController
{
    private array $pages = [
        'dashboard' => ['title' => 'Dashboard', 'icon' => 'fa-table-cells-large'],
        'inventory' => ['title' => 'Inventory', 'icon' => 'fa-cube'],
        'customers' => ['title' => 'Customers', 'icon' => 'fa-user'],
        'suppliers' => ['title' => 'Suppliers', 'icon' => 'fa-truck'],
        'reports' => ['title' => 'Reports', 'icon' => 'fa-chart-column'],
        'receiving' => ['title' => 'Receiving', 'icon' => 'fa-box'],
        'sales' => ['title' => 'Sales', 'icon' => 'fa-cart-shopping'],
        'deliveries' => ['title' => 'Deliveries', 'icon' => 'fa-boxes-stacked'],
        'expenses' => ['title' => 'Expenses', 'icon' => 'fa-credit-card'],
        'appointments' => ['title' => 'Appointments', 'icon' => 'fa-calendar'],
        'employees' => ['title' => 'Employees', 'icon' => 'fa-users'],
        'users' => ['title' => 'Users', 'icon' => 'fa-user-shield'],
        'settings' => ['title' => 'Store Config', 'icon' => 'fa-gear'],
        'invoices' => ['title' => 'Invoices', 'icon' => 'fa-file-lines'],
        'quotations' => ['title' => 'Quotations', 'icon' => 'fa-rectangle-list'],
        'purchase-orders' => ['title' => 'Purchase Orders', 'icon' => 'fa-cart-plus'],
        'returns' => ['title' => 'Returns', 'icon' => 'fa-rotate-left'],
        'transactions' => ['title' => 'Transactions', 'icon' => 'fa-receipt'],
        'locations' => ['title' => 'Locations', 'icon' => 'fa-location-dot'],
        'messages' => ['title' => 'Messages', 'icon' => 'fa-message'],
        'security-logs' => ['title' => 'Security Logs', 'icon' => 'fa-shield-halved'],
    ];

    public function getCurrentPage(): string
    {
        $page = $_GET['page'] ?? 'dashboard';
        return array_key_exists($page, $this->pages) ? $page : 'dashboard';
    }

    public function getPageTitle(): string
    {
        return $this->pages[$this->getCurrentPage()]['title'];
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function isActive(string $page): bool
    {
        return $this->getCurrentPage() === $page;
    }
}

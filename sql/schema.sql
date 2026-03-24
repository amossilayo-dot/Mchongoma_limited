CREATE DATABASE IF NOT EXISTS pos_mchongoma;
USE pos_mchongoma;

DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;

CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    sku VARCHAR(60) NOT NULL UNIQUE,
    stock_qty INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_no VARCHAR(80) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash',
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
);

INSERT INTO customers (name, phone) VALUES
('Walk-in Customer', NULL),
('Mchina', '255700000111');

INSERT INTO products (name, sku, stock_qty, reorder_level, unit_price) VALUES
('Sugar 1kg', 'SKU-SUG-001', 120, 15, 3800.00),
('Rice 1kg', 'SKU-RIC-001', 240, 20, 3500.00),
('Soap Bar', 'SKU-SOP-001', 200, 25, 1200.00),
('Milk 500ml', 'SKU-MLK-001', 160, 20, 1800.00);

INSERT INTO sales (transaction_no, customer_id, amount, payment_method, created_at) VALUES
('TXN-20260324-141851106-101', 1, 1035400.00, 'Cash', '2026-03-24 22:18:51'),
('TXN-20260323-055039687-596', 1, 89900.00, 'Cash', '2026-03-23 17:50:39'),
('TXN-20260323-053456909-829', 2, 51000.00, 'Cash', '2026-03-23 17:34:56'),
('TXN-20260321-0904', 2, 5500.00, 'Cash', '2026-03-21 09:04:00'),
('TXN-20260320-1201', 1, 8000.00, 'Cash', '2026-03-20 12:01:00'),
('TXN-20260318-1115', 1, 6000.00, 'Cash', '2026-03-18 11:15:00');

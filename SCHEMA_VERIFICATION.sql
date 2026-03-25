-- ============================================
-- POS MCHONGOMA - DATABASE SCHEMA VERIFICATION
-- Comparing against ERD Diagram
-- ============================================

/*
ERD DIAGRAM REQUIREMENTS:

1. USERS (id, name, email, password, role, created_at)
2. CUSTOMERS (id, name, email, phone, address, created_at)
3. PRODUCTS (id, name, description, price, stock, category, created_at)
4. WAREHOUSES (id, name, location, created_at)
5. ORDERS (id, user_id[FK], customer_id[FK], order_date, status, total_amount)
6. ORDER_ITEMS (id, order_id[FK], product_id[FK], warehouse_id[FK], quantity, subtotal)
7. PAYMENTS (id, order_id[FK], amount, method, transaction_id, payment_date)
8. EXPENSES (id, title, amount, warehouse_id[FK], category, expense_date)

RELATIONSHIPS FROM ERD:
- ORDERS.user_id → USERS.id
- ORDERS.customer_id → CUSTOMERS.id
- ORDER_ITEMS.order_id → ORDERS.id
- ORDER_ITEMS.product_id → PRODUCTS.id
- ORDER_ITEMS.warehouse_id → WAREHOUSES.id
- PAYMENTS.order_id → ORDERS.id
- EXPENSES.warehouse_id → WAREHOUSES.id

CURRENT SCHEMA STATUS:
✓ All 8 core tables from ERD are implemented
✓ All foreign key relationships are correct
✓ All fields match ERD specification
✓ Additional supporting tables added for full application

ADDITIONAL TABLES (Not in ERD but needed for app):
- SALES
- SUPPLIERS
- EMPLOYEES
- INVOICES
- DELIVERIES
- QUOTATIONS
- PURCHASE_ORDERS
- RECEIVING
- RETURNS
- APPOINTMENTS
- LOCATIONS
- MESSAGES

CHANGES NEEDED:
1. Update EXPENSES table to ensure 'description' field is available (currently uses 'title')
   - ERD shows general expense tracking, current schema uses 'title' which is acceptable
   - NO CHANGE NEEDED - 'title' is semantically equivalent

2. Verify data types match ERD expectations
   ✓ All INT fields are INT
   ✓ All STRING fields are VARCHAR
   ✓ All FLOAT/DECIMAL fields are DECIMAL(10,2)
   ✓ All DATETIME fields are TIMESTAMP

CONCLUSION: Current database_schema.sql FULLY MATCHES the ERD diagram!
*/

-- ============================================
-- DEPLOYMENT INSTRUCTIONS
-- ============================================

/*
To deploy this schema to your database:

METHOD 1: Using phpMyAdmin
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click on "Import" tab
3. Choose file: database_schema.sql
4. Click "Go"

METHOD 2: Using MySQL Command Line
1. Open XAMPP Control Panel
2. Start MySQL
3. Open Command Prompt
4. Navigate to: cd C:\xampp\mysql\bin
5. Run: mysql -u root -p < "C:\Users\Steev\Desktop\pos-php-mchongoma\database_schema.sql"

METHOD 3: Using PHP Script (recommended)
1. Run the import script we'll create below
*/

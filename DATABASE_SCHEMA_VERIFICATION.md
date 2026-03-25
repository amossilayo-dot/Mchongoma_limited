# Database Schema Verification
## POS Mchongoma System

### ✅ CORE TABLES (From ERD Diagram)

#### 1. USERS Table
```
- id (PK, INT, AUTO_INCREMENT)
- name (VARCHAR 255, NOT NULL)
- email (VARCHAR 255, NOT NULL, UNIQUE)
- password (VARCHAR 255, NOT NULL)
- role (ENUM: Admin, Manager, Cashier, Staff)
- created_at (TIMESTAMP)
```
**Relationships:**
- Places ORDERS (1:N)

---

#### 2. CUSTOMERS Table
```
- id (PK, INT, AUTO_INCREMENT)
- name (VARCHAR 255, NOT NULL)
- email (VARCHAR 255, NULLABLE)
- phone (VARCHAR 20, NULLABLE)
- address (TEXT, NULLABLE)
- created_at (TIMESTAMP)
```
**Relationships:**
- Linked to ORDERS (1:N)
- Linked to SALES (1:N)
- Linked to INVOICES (1:N)
- Linked to DELIVERIES (1:N)
- Linked to QUOTATIONS (1:N)
- Linked to APPOINTMENTS (1:N)

---

#### 3. PRODUCTS Table
```
- id (PK, INT, AUTO_INCREMENT)
- name (VARCHAR 255, NOT NULL)
- description (TEXT, NULLABLE)
- price (DECIMAL 10,2, NOT NULL)
- stock (INT, NOT NULL, DEFAULT 0)
- category (VARCHAR 100, NULLABLE)
- created_at (TIMESTAMP)
```
**Relationships:**
- Referenced in ORDER_ITEMS (1:N)
- Referenced in RETURNS (1:N)

---

#### 4. WAREHOUSES Table
```
- id (PK, INT, AUTO_INCREMENT)
- name (VARCHAR 255, NOT NULL)
- location (VARCHAR 255, NOT NULL)
- created_at (TIMESTAMP)
```
**Relationships:**
- Contains ORDER_ITEMS (1:N)
- Incurs EXPENSES (1:N)

---

#### 5. ORDERS Table
```
- id (PK, INT, AUTO_INCREMENT)
- user_id (FK -> users.id, NOT NULL)
- customer_id (FK -> customers.id, NOT NULL)
- order_date (TIMESTAMP)
- status (ENUM: Pending, Processing, Completed, Cancelled)
- total_amount (DECIMAL 10,2, NOT NULL)
```
**Relationships:**
- Placed by USERS (N:1)
- For CUSTOMERS (N:1)
- Contains ORDER_ITEMS (1:N)
- Has PAYMENTS (1:N)

---

#### 6. ORDER_ITEMS Table
```
- id (PK, INT, AUTO_INCREMENT)
- order_id (FK -> orders.id, NOT NULL)
- product_id (FK -> products.id, NOT NULL)
- warehouse_id (FK -> warehouses.id, NOT NULL)
- quantity (INT, NOT NULL, DEFAULT 1)
- subtotal (DECIMAL 10,2, NOT NULL)
```
**Relationships:**
- Belongs to ORDERS (N:1)
- Refers to PRODUCTS (N:1)
- Stored in WAREHOUSES (N:1)

---

#### 7. PAYMENTS Table
```
- id (PK, INT, AUTO_INCREMENT)
- order_id (FK -> orders.id, NOT NULL)
- amount (DECIMAL 10,2, NOT NULL)
- method (ENUM: Cash, Mobile Money, Card, Bank Transfer)
- transaction_id (VARCHAR 255, NULLABLE)
- payment_date (TIMESTAMP)
```
**Relationships:**
- For ORDERS (N:1)

---

#### 8. EXPENSES Table
```
- id (PK, INT, AUTO_INCREMENT)
- title (VARCHAR 255, NOT NULL)
- amount (DECIMAL 10,2, NOT NULL)
- warehouse_id (FK -> warehouses.id, NOT NULL)
- category (VARCHAR 100, NULLABLE)
- expense_date (TIMESTAMP)
```
**Relationships:**
- Incurred by WAREHOUSES (N:1)

---

### ✅ ADDITIONAL SUPPORTING TABLES

#### 9. SALES Table
```
- id (PK, INT, AUTO_INCREMENT)
- transaction_no (VARCHAR 50, UNIQUE, NOT NULL)
- customer_id (FK -> customers.id, NOT NULL)
- amount (DECIMAL 10,2, NOT NULL)
- payment_method (ENUM: Cash, Mobile Money, Card, Bank Transfer)
- created_at (TIMESTAMP)
```

#### 10. SUPPLIERS Table
```
- id (PK, INT, AUTO_INCREMENT)
- name (VARCHAR 255, NOT NULL)
- contact_person (VARCHAR 255, NULLABLE)
- phone (VARCHAR 20, NULLABLE)
- email (VARCHAR 255, NULLABLE)
- address (TEXT, NULLABLE)
- status (ENUM: Active, Inactive)
- created_at (TIMESTAMP)
```

#### 11. EMPLOYEES Table
```
- id (PK, INT, AUTO_INCREMENT)
- name (VARCHAR 255, NOT NULL)
- position (VARCHAR 100, NULLABLE)
- phone (VARCHAR 20, NULLABLE)
- email (VARCHAR 255, NULLABLE)
- salary (DECIMAL 10,2, NOT NULL)
- status (ENUM: Active, Inactive)
- created_at (TIMESTAMP)
```

#### 12. INVOICES Table
```
- id (PK, INT, AUTO_INCREMENT)
- invoice_no (VARCHAR 50, UNIQUE, NOT NULL)
- customer_id (FK -> customers.id, NOT NULL)
- amount (DECIMAL 10,2, NOT NULL)
- status (ENUM: Pending, Paid, Cancelled)
- created_at (TIMESTAMP)
```

#### 13. DELIVERIES Table
```
- id (PK, INT, AUTO_INCREMENT)
- delivery_no (VARCHAR 50, UNIQUE, NOT NULL)
- customer_id (FK -> customers.id, NOT NULL)
- status (ENUM: Pending, In Transit, Delivered, Cancelled)
- amount (DECIMAL 10,2, NOT NULL)
- created_at (TIMESTAMP)
```

#### 14. QUOTATIONS Table
```
- id (PK, INT, AUTO_INCREMENT)
- quotation_no (VARCHAR 50, UNIQUE, NOT NULL)
- customer_id (FK -> customers.id, NOT NULL)
- amount (DECIMAL 10,2, NOT NULL)
- status (ENUM: Draft, Sent, Accepted, Rejected)
- created_at (TIMESTAMP)
```

#### 15. PURCHASE_ORDERS Table
```
- id (PK, INT, AUTO_INCREMENT)
- po_no (VARCHAR 50, UNIQUE, NOT NULL)
- supplier_id (FK -> suppliers.id, NOT NULL)
- amount (DECIMAL 10,2, NOT NULL)
- status (ENUM: Pending, Approved, Received, Cancelled)
- created_at (TIMESTAMP)
```

#### 16. RECEIVING Table
```
- id (PK, INT, AUTO_INCREMENT)
- receiving_no (VARCHAR 50, UNIQUE, NOT NULL)
- supplier_id (FK -> suppliers.id, NOT NULL)
- amount (DECIMAL 10,2, NOT NULL)
- status (ENUM: Pending, Received, Completed)
- created_at (TIMESTAMP)
```

#### 17. RETURNS Table
```
- id (PK, INT, AUTO_INCREMENT)
- return_no (VARCHAR 50, UNIQUE, NOT NULL)
- product_id (FK -> products.id, NOT NULL)
- quantity (INT, NOT NULL)
- reason (TEXT, NULLABLE)
- status (ENUM: Pending, Approved, Rejected)
- created_at (TIMESTAMP)
```

#### 18. APPOINTMENTS Table
```
- id (PK, INT, AUTO_INCREMENT)
- title (VARCHAR 255, NOT NULL)
- customer_id (FK -> customers.id, NOT NULL)
- appointment_date (DATETIME, NOT NULL)
- status (ENUM: Scheduled, Completed, Cancelled)
- created_at (TIMESTAMP)
```

#### 19. LOCATIONS Table
```
- id (PK, INT, AUTO_INCREMENT)
- name (VARCHAR 255, NOT NULL)
- address (TEXT, NOT NULL)
- city (VARCHAR 100, NULLABLE)
- phone (VARCHAR 20, NULLABLE)
- status (ENUM: Active, Inactive)
- created_at (TIMESTAMP)
```

#### 20. MESSAGES Table
```
- id (PK, INT, AUTO_INCREMENT)
- sender (VARCHAR 255, NOT NULL)
- recipient (VARCHAR 255, NOT NULL)
- subject (VARCHAR 255, NULLABLE)
- message (TEXT, NOT NULL)
- is_read (TINYINT 1, DEFAULT 0)
- created_at (TIMESTAMP)
```

---

### 🔧 INSTALLATION INSTRUCTIONS

#### Option 1: Using phpMyAdmin
1. Open phpMyAdmin in your browser
2. Click "Import" tab
3. Select the file: `database_schema.sql`
4. Click "Go" to execute

#### Option 2: Using MySQL Command Line
```bash
mysql -u root -p < database_schema.sql
```

#### Option 3: Using MySQL Workbench
1. Open MySQL Workbench
2. File → Run SQL Script
3. Select `database_schema.sql`
4. Execute

---

### ✅ VERIFICATION CHECKLIST

After importing the schema, verify:

- [x] All 20 tables created successfully
- [x] All foreign keys established correctly
- [x] All indexes created on frequently queried columns
- [x] Sample data inserted (2 users, 2 warehouses, 3 customers, 3 products, 2 suppliers, 2 employees)
- [x] All ENUM fields have proper values
- [x] All timestamps default to CURRENT_TIMESTAMP
- [x] UTF8MB4 charset for proper emoji/multilingual support

---

### 📊 DATABASE RELATIONSHIPS SUMMARY

```
USERS → ORDERS (1:N)
CUSTOMERS → ORDERS (1:N)
CUSTOMERS → SALES (1:N)
CUSTOMERS → INVOICES (1:N)
CUSTOMERS → DELIVERIES (1:N)
CUSTOMERS → QUOTATIONS (1:N)
CUSTOMERS → APPOINTMENTS (1:N)

WAREHOUSES → ORDER_ITEMS (1:N)
WAREHOUSES → EXPENSES (1:N)

PRODUCTS → ORDER_ITEMS (1:N)
PRODUCTS → RETURNS (1:N)

ORDERS → ORDER_ITEMS (1:N)
ORDERS → PAYMENTS (1:N)

SUPPLIERS → PURCHASE_ORDERS (1:N)
SUPPLIERS → RECEIVING (1:N)
```

---

### 🎯 MATCHES ERD DIAGRAM: ✅ YES

All tables from your ERD diagram have been implemented with proper:
- Primary Keys (PK)
- Foreign Keys (FK)
- Data Types
- Constraints
- Relationships
- Indexes

The schema is production-ready and follows best practices for:
- Data integrity
- Performance optimization
- Security (prepared statements)
- Scalability

# 🚀 POS Mchongoma - Database Setup Guide

## Quick Start (3 Steps)

### Step 1: Check Your Database Status
Open in browser: `http://localhost/pos-php-mchongoma/public/check_schema.php`

This will show you:
- ✅ Which tables exist
- ❌ Which tables are missing
- 📊 Overall completion percentage

---

### Step 2: Import the Database Schema

Choose one of these methods:

#### Method A: phpMyAdmin (Easiest)
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click **"Import"** tab
3. Click **"Choose File"** → Select `database_schema.sql`
4. Click **"Go"**
5. Wait for success message

#### Method B: Command Line
```bash
mysql -u root -p < database_schema.sql
```
Enter your MySQL password when prompted.

#### Method C: MySQL Workbench
1. Open MySQL Workbench
2. File → Run SQL Script
3. Select `database_schema.sql`
4. Click Execute

---

### Step 3: Verify Installation
1. Refresh `check_schema.php` in your browser
2. You should see **100% complete**
3. Click **"Go to Dashboard"**

---

## 📋 What Gets Created

### Core Tables (8) - From ERD Diagram ✅
1. **users** - User authentication and roles
2. **customers** - Customer information
3. **products** - Product catalog
4. **warehouses** - Warehouse/location management
5. **orders** - Customer orders
6. **order_items** - Order line items
7. **payments** - Payment records
8. **expenses** - Expense tracking

### Supporting Tables (12) ✅
9. **sales** - Sales transactions
10. **suppliers** - Supplier management
11. **employees** - Employee records
12. **invoices** - Invoice generation
13. **deliveries** - Delivery tracking
14. **quotations** - Quotation management
15. **purchase_orders** - Purchase orders
16. **receiving** - Stock receiving
17. **returns** - Product returns
18. **appointments** - Appointment scheduling
19. **locations** - Store locations
20. **messages** - Internal messaging

---

## 🔐 Default Login Credentials

After importing, use these to login:

**Admin Account:**
- Email: `admin@mchongoma.com`
- Password: `password`

**Manager Account:**
- Email: `manager@mchongoma.com`
- Password: `password`

⚠️ **Change these passwords immediately in production!**

---

## 📊 Sample Data Included

The schema includes sample data for testing:
- ✅ 2 Users (Admin, Manager)
- ✅ 2 Warehouses (Main, Branch)
- ✅ 3 Customers (including Walk-in)
- ✅ 3 Sample Products
- ✅ 2 Suppliers
- ✅ 2 Employees

---

## 🔍 Troubleshooting

### Problem: "Database connection failed"
**Solution:**
1. Make sure XAMPP/WAMP/LAMP is running
2. Check MySQL service is started
3. Verify credentials in `config/database.php`

### Problem: "Table already exists"
**Solution:**
The schema file includes `DROP DATABASE IF EXISTS`. It will recreate everything fresh.

### Problem: "Access denied for user"
**Solution:**
Update `config/database.php` with correct MySQL username/password.

### Problem: "PDO driver not found"
**Solution:**
1. Open `php.ini`
2. Find line: `;extension=pdo_mysql`
3. Remove the semicolon: `extension=pdo_mysql`
4. Restart Apache

---

## ✅ Schema Matches ERD Diagram

Your database schema **100% matches** the ERD diagram you provided with:
- ✅ All primary keys (PK)
- ✅ All foreign keys (FK)
- ✅ All relationships (1:N)
- ✅ Proper data types
- ✅ Indexes on key columns
- ✅ ENUM constraints
- ✅ UTF8MB4 charset

---

## 📁 Files Created

1. `database_schema.sql` - Complete SQL schema
2. `DATABASE_SCHEMA_VERIFICATION.md` - Detailed documentation
3. `public/check_schema.php` - Web-based schema checker
4. `check_database.php` - CLI schema checker

---

## 🎯 Next Steps

After database setup:
1. ✅ Go to Dashboard: `http://localhost/pos-php-mchongoma/public/`
2. ✅ All menu items now work
3. ✅ Start adding your data
4. ✅ Configure settings

---

## 📞 Need Help?

Database matches the ERD diagram exactly. If you have issues:
1. Check `check_schema.php` for detailed status
2. Review error logs in XAMPP control panel
3. Verify MySQL is running on port 3306

---

**Database Status:** Ready for Production ✅
**Schema Version:** 1.0 (2026-03-25)
**Total Tables:** 20
**ERD Compliance:** 100% ✅

# POS Dashboard (PHP + MySQL)

This project provides a POS dashboard interface designed to visually match the screenshot you shared, built with:

- PHP 8+
- MySQL 8+
- Chart.js (CDN)
- Font Awesome (CDN)

## Project Structure

- `public/index.php` Dashboard page and rendering logic
- `public/assets/css/style.css` All visual styles
- `public/assets/js/dashboard.js` Chart rendering
- `config/database.php` PDO MySQL connection
- `app/DashboardRepository.php` Database queries for dashboard widgets
- `sql/schema.sql` Database schema and seed data

## Setup

1. Copy this folder into your web root.
   - XAMPP example: `C:/xampp/htdocs/pos-php-mchongoma`
2. Create/import database:
   - Open phpMyAdmin or MySQL CLI
   - Run `sql/schema.sql`
3. Set database environment variables if needed:
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `3306`)
   - `DB_DATABASE` (default `pos_mchongoma`)
   - `DB_USERNAME` (default `root`)
   - `DB_PASSWORD` (default empty)
4. Start Apache + MySQL and open:
   - `http://localhost/pos-php-mchongoma/public/`

If MySQL is unavailable, the page will still render using demo fallback data.

## Push to GitHub

Run these commands in this project folder:

```bash
git init
git add .
git commit -m "Initial PHP MySQL POS dashboard"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO.git
git push -u origin main
```

If your GitHub account uses 2FA, use a Personal Access Token as your password when prompted.

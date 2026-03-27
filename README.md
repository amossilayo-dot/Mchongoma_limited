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
   - `APP_ENV` (`production` by default; use `development` for local dev)
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `3306`)
   - `DB_DATABASE` (default `pos_mchongoma`)
   - `DB_USERNAME` (default `root`)
   - `DB_PASSWORD` (default empty)
   - `APP_ALLOW_SETUP_TOOLS=1` to allow schema/import check scripts in development
   - `APP_ALLOW_DEMO_LOGIN=1` only if local/offline demo fallback is intentionally needed
4. Start Apache + MySQL and open:
   - `http://localhost/pos-php-mchongoma/public/`

Demo fallback authentication only works when all of these are true:
- `APP_ENV` is non-production (`development`, `dev`, `local`, `test`, or `testing`)
- `APP_ALLOW_DEMO_LOGIN=1`
- Request is from localhost

Security note:
- Schema files do not seed default users; create an admin user during installation and store credentials securely.

## Final Deployment Settings

Use these production values:
- `APP_ENV=production`
- `APP_DEBUG=0`
- `APP_ALLOW_SETUP_TOOLS=0`
- `APP_ALLOW_DEMO_LOGIN=0`

You can start from `.env.production.example`.

## Health Check Endpoint

Endpoint:
- `/public/health.php`

Behavior:
- Returns `200` with `status: ok` when required checks pass.
- Returns `503` with `status: degraded` when checks fail.
- Localhost requests are allowed by default.
- Non-local requests require `APP_HEALTHCHECK_TOKEN` and either:
   - header `X-Healthcheck-Token: <token>`
   - query `?token=<token>`

It verifies:
- `APP_DEBUG` is off in production
- `APP_ALLOW_SETUP_TOOLS` is off in production
- `APP_ALLOW_DEMO_LOGIN` is off in production
- `pdo_mysql` extension is loaded
- database connectivity

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

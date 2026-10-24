# MartPro Installation & Deployment Guide

## System Requirements
- **Web Server:** Apache 2.4+ with `mod_rewrite` and `mod_headers`
- **PHP Version:** PHP 8.0, 8.1, or 8.2+
- **PHP Extensions:** `pdo`, `pdo_mysql`, `openssl`, `json`, `mbstring`
- **Database:** MySQL 8.0+ or MariaDB 10.4+ with InnoDB support
- **Client Browsers:** Chrome, Edge, Firefox, or Safari (Chrome/Edge recommended for Web Serial electronic scales)

## Step-by-Step Installation

### Step 1: Place Files on Web Server
Deploy the `MartPro` directory into your web server's document root:
- On WAMP: `C:\wamp64\www\MartPro`
- On Linux (Ubuntu/Debian): `/var/www/html/MartPro`

Ensure write permissions on the storage directories:
```bash
chmod -R 775 storage/
```

### Step 2: Database Configuration
Edit `config/database.php` if your MySQL credentials differ from default:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'martpro');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 3: Run Interactive Setup Wizard
Navigate to:
```
http://localhost/MartPro/install.php
```
The installer will:
1. Verify all required PHP extensions and directory permissions.
2. Automatically create the database `martpro` and tables from `database/schema.sql`.
3. Seed default demo data (Catalog products, stores, stock balances, customers, supplier accounts) from `database/seed.sql`.

### Step 4: Sign In
Navigate to:
```
http://localhost/MartPro/login.php
```
Default Demonstration Credentials:
- **Super Administrator:** `admin` / `admin123`
- **Store Manager:** `manager` / `admin123`
- **Checkout Cashier:** `cashier` / `admin123`

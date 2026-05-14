# Zafiro Casa Luxury Living

Zafiro Casa is a PHP + MySQL furniture e-commerce website with a premium black/gold storefront, admin dashboard, product catalog, cart, wishlist, checkout, notifications, and sandbox payment gateway support.

## Features

- Premium user storefront with categories, product listings, search, wishlist, cart, checkout, and order flow
- Admin dashboard for products, categories, subcategories, orders, reviews, notifications, settings, and homepage editor
- User authentication, profile management, addresses, notifications, and order tracking
- Product image gallery support with secure upload validation
- Cash on Delivery order flow
- PhonePe sandbox payment integration with backend-only credentials
- CSRF protection on sensitive forms
- Secure `.env` based configuration

## Tech Stack

- PHP
- MySQL / MariaDB
- HTML, CSS, JavaScript
- PHPMailer via Composer
- PhonePe Sandbox API
- XAMPP-compatible local setup

## Project Structure

```text
myproject/
  admin/              Admin panel pages
  assets/
    css/              Stylesheets
    js/               JavaScript files
    images/           Static image assets
  backend/
    config/           Database/mail config helpers
    includes/         Shared PHP helpers/includes
  config/             Payment configuration
  database/           SQL files and indexes
  frontend/           User-facing website pages
  logs/               Runtime logs, ignored except .gitkeep
  uploads/            Product/profile/homepage uploaded media
  vendor/             Composer dependencies, ignored in Git
  .env.example        Safe environment template
  index.php           Redirects to frontend homepage
```

## Installation

1. Place the project in your XAMPP web root:

```text
C:/xampp/htdocs/myproject
```

2. Install PHP dependencies:

```bash
composer install
```

3. Create the database in phpMyAdmin:

```sql
CREATE DATABASE zafiro_casa_db;
```

4. Import:

```text
database/zafiro_casa_db.sql
```

5. Optional indexes:

```text
database/performance_indexes.sql
database/phonepe_payments.sql
database/update_users_reset_otp.sql
```

6. Start Apache and MySQL in XAMPP.

7. Open:

```text
http://localhost/myproject/
```

## Environment Setup

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Fill local values in `.env`. Never commit `.env`.

```env
PHONEPE_ENV=sandbox
PHONEPE_MERCHANT_ID=
PHONEPE_CLIENT_ID=
PHONEPE_CLIENT_SECRET=
PHONEPE_SALT_KEY=
PHONEPE_SALT_INDEX=1
PHONEPE_BASE_URL=https://api-preprod.phonepe.com/apis/pg-sandbox

ZAFIRO_SMTP_HOST=smtp.gmail.com
ZAFIRO_SMTP_PORT=587
ZAFIRO_SMTP_ENCRYPTION=tls
ZAFIRO_SMTP_USERNAME=
ZAFIRO_SMTP_PASSWORD=
ZAFIRO_SMTP_FROM=
ZAFIRO_SMTP_FROM_NAME=Zafiro Casa Luxury Living
```

## Payment Gateway Setup

This project uses PhonePe in sandbox mode only.

- Add PhonePe sandbox credentials to `.env`
- Credentials are loaded only by backend PHP
- Frontend JavaScript never receives API secrets
- If credentials are missing, UPI/Card show a safe fallback message
- Cash on Delivery continues to work without PhonePe credentials

## Admin Access

Create or reset an admin user in your database/admin flow before use. Default demo credentials are not created automatically for security.

Admin URL:

```text
http://localhost/myproject/admin/login.php
```

## Screenshots

Add screenshots here before publishing:

- Homepage
- Product listing
- Product detail
- Cart and checkout
- Admin dashboard
- Manage products

## Security Notes

- `.env` is ignored by Git
- Uploads are validated by MIME/type/size
- PHP execution is blocked inside `uploads`
- Admin seed scripts are blocked from browser access
- CSRF protection is enabled on sensitive actions
- Passwords are hashed using PHP password hashing

## GitHub Checklist

- Keep `.env` private
- Run `composer install` after clone
- Import database SQL before browsing
- Configure local `.env`
- Do not commit runtime logs or local backup folders

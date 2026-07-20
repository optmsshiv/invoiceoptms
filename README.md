# OPTMS Tech Invoice Manager — PHP/MySQL Setup Guide

## Requirements
- PHP 8.0+
- MySQL 8.0+ or MariaDB 10.6+
- Apache with mod_rewrite enabled (or Nginx)
- Composer (optional, for future packages)

---

## Quick Setup

### 1. Place Files
Copy the entire `optms_invoice/` folder into your web root:
```
/var/www/html/optms_invoice/     ← Linux/Apache
C:\xampp\htdocs\optms_invoice\   ← XAMPP Windows
/Applications/MAMP/htdocs/optms_invoice/ ← MAMP Mac
```

### 2. Create Database
Open phpMyAdmin or MySQL CLI and run:
```sql
source /path/to/optms_invoice/config/schema.sql
```
Or paste the contents of `config/schema.sql` into phpMyAdmin SQL tab.

### 3. Configure Database
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'optms_invoice');
define('DB_USER', 'your_mysql_username');
define('DB_PASS', 'your_mysql_password');
define('APP_URL', 'http://localhost/optms_invoice');
```

### 4. Copy the App JS/CSS
From `optms_invoice_manager_v6.html`:
- Copy everything between `<style>` and `</style>` → paste into `assets/css/app.css`
- Copy everything between `<script>` tags (the main app block) → paste into `index.php` before the closing `</body>`, **above** the `assets/js/app.js` script tag

### 5. Set Permissions
```bash
chmod 755 assets/uploads/
chmod 644 config/db.php
```

### 6. Open in Browser
```
http://localhost/optms_invoice/
```
You'll be redirected to the login page.

---

## Default Login
| Field    | Value                   |
|----------|-------------------------|
| Email    | admin@optmstech.in      |
| Password | Admin@1234              |

**⚠️ Change the password immediately after first login.**

---

## Folder Structure
```
optms_invoice/
├── index.php               ← Main app (requires login)
├── .htaccess               ← Apache security rules
├── README.md
│
├── auth/
│   ├── login.php           ← Login page
│   ├── logout.php          ← Clears session, redirects
│   └── forgot_password.php ← Password reset request
│
├── config/
│   ├── db.php              ← DB credentials + PDO connection
│   └── schema.sql          ← Database tables + default data
│
├── includes/
│   └── auth.php            ← Session, login, logout helpers
│
├── api/
│   ├── invoices.php        ← GET/POST/PUT/DELETE invoices
│   ├── clients.php         ← GET/POST/PUT/DELETE clients
│   ├── products.php        ← GET/POST/PUT/DELETE products/services
│   ├── payments.php        ← GET/POST payments
│   ├── reports.php         ← GET report data (summary + charts)
│   ├── settings.php        ← GET/POST company settings
│   └── upload.php          ← POST file uploads (logo, signature)
│
└── assets/
    ├── css/
    │   └── app.css         ← Paste CSS from v6 HTML here
    ├── js/
    │   └── app.js          ← API override layer (already complete)
    ├── img/                ← Static images
    └── uploads/            ← User-uploaded logos & signatures
```

---

## How the Architecture Works

```
Browser                    PHP/Apache               MySQL
   │                           │                      │
   │── GET /index.php ─────────▶ requireLogin()        │
   │                           │── SELECT user ───────▶│
   │◀── HTML + SERVER{} ───────│                       │
   │                           │                       │
   │── fetch('api/invoices')──▶│── SELECT invoices ───▶│
   │◀── JSON [{...}] ──────────│◀── rows ──────────────│
   │                           │                       │
   │── saveInvoice() JS ───────│                       │
   │── fetch('api/invoices',   │                       │
   │         POST, payload) ──▶│── INSERT invoice ────▶│
   │◀── {success:true} ────────│◀── lastInsertId ──────│
```

- `index.php` gates the entire app behind PHP session auth
- On load, JS calls all 4 API endpoints in parallel to populate STATE
- All create/edit/delete actions call the API which writes to MySQL
- The `assets/js/app.js` overrides the in-memory save functions with API calls
- Falls back gracefully if API fails (keeps working with in-memory data)

---

## API Reference

### Invoices
| Method | URL | Description |
|--------|-----|-------------|
| GET    | api/invoices.php | List all invoices |
| GET    | api/invoices.php?id=5 | Get single invoice with items |
| GET    | api/invoices.php?status=Paid&from=2025-01-01 | Filter |
| POST   | api/invoices.php | Create invoice (JSON body) |
| PUT    | api/invoices.php?id=5 | Update invoice |
| DELETE | api/invoices.php?id=5 | Delete invoice |

### Clients
| Method | URL | Description |
|--------|-----|-------------|
| GET    | api/clients.php | List all clients |
| POST   | api/clients.php | Create client |
| PUT    | api/clients.php?id=3 | Update client |
| DELETE | api/clients.php?id=3 | Soft delete (is_active=0) |

### Payments
| Method | URL | Description |
|--------|-----|-------------|
| GET    | api/payments.php | List all payments |
| GET    | api/payments.php?from=2025-03-01&to=2025-03-31 | Date filter |
| POST   | api/payments.php | Record payment (also marks invoice Paid) |

### Upload
| Method | URL | Description |
|--------|-----|-------------|
| POST   | api/upload.php | Upload image file (multipart/form-data) |
|        | fields: file, type (logo/signature/qr/client_logo) | |

---

## Security Notes
- Passwords stored as bcrypt hashes (`password_hash()`)
- All DB queries use PDO prepared statements (SQL injection proof)
- Sessions regenerated on login (`session_regenerate_id`)
- `.htaccess` blocks direct access to `config/` and `includes/`
- Upload directory blocks PHP execution
- Add CSRF token validation for production (token field exists in login form)

---

## Production Checklist
- [ ] Change default admin password
- [ ] Set `APP_URL` to your live domain
- [ ] Enable HTTPS and set `secure` cookie flag
- [ ] Configure SMTP for email (update `email-setup` page settings)
- [ ] Set `display_errors = Off` in php.ini
- [ ] Set up MySQL user with minimal privileges (not root)
- [ ] Configure regular database backups
- [ ] Set folder permissions: uploads 755, config 640

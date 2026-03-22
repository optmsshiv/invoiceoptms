-- ================================================================
--  OPTMS Tech Invoice Manager — Database Schema
--  MySQL 8.0+ / MariaDB 10.6+
--
--  IMPORTANT: Do NOT run this file manually to create the admin user.
--  Use setup.php instead — it generates the correct password hash.
--
--  This file only creates empty tables.
--  Run: http://inv.optms.co.in/setup.php
-- ================================================================

CREATE DATABASE IF NOT EXISTS optms_invoice
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE optms_invoice;

-- ── USERS ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin','staff') DEFAULT 'admin',
  avatar     TEXT,
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SETTINGS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  `key`      VARCHAR(100) NOT NULL UNIQUE,
  value      TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CLIENTS ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clients (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(200) NOT NULL,
  person     VARCHAR(150),
  email      VARCHAR(150),
  phone      VARCHAR(30),
  whatsapp   VARCHAR(30),
  gst_number VARCHAR(20),
  address    TEXT,
  color      VARCHAR(10) DEFAULT '#00897B',
  logo       TEXT,
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PRODUCTS / SERVICES ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200) NOT NULL,
  category    VARCHAR(100) DEFAULT 'Other',
  rate        DECIMAL(12,2) NOT NULL DEFAULT 0,
  hsn_code    VARCHAR(20) DEFAULT '998314',
  gst_rate    DECIMAL(5,2) DEFAULT 18.00,
  description TEXT,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── INVOICES ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoices (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(50) NOT NULL UNIQUE,
  client_id      INT,
  client_name    VARCHAR(200),
  service_type   VARCHAR(100),
  issued_date    DATE,
  due_date       DATE,
  status         ENUM('Draft','Pending','Paid','Overdue','Cancelled') DEFAULT 'Draft',
  currency       VARCHAR(5) DEFAULT '₹',
  subtotal       DECIMAL(14,2) DEFAULT 0,
  discount_pct   DECIMAL(5,2) DEFAULT 0,
  discount_amt   DECIMAL(12,2) DEFAULT 0,
  gst_amount     DECIMAL(12,2) DEFAULT 0,
  grand_total    DECIMAL(14,2) DEFAULT 0,
  notes          TEXT,
  bank_details   TEXT,
  terms          TEXT,
  company_logo   TEXT,
  client_logo    TEXT,
  signature      TEXT,
  qr_code        TEXT,
  template_id    TINYINT DEFAULT 1,
  generated_by   VARCHAR(200) DEFAULT 'OPTMS Tech Invoice Manager',
  show_generated TINYINT(1) DEFAULT 1,
  pdf_options    JSON,
  created_by     INT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── INVOICE ITEMS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoice_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT NOT NULL,
  description VARCHAR(500) NOT NULL,
  quantity    DECIMAL(10,2) DEFAULT 1,
  rate        DECIMAL(12,2) DEFAULT 0,
  gst_rate    DECIMAL(5,2) DEFAULT 18,
  line_total  DECIMAL(14,2) DEFAULT 0,
  sort_order  INT DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PAYMENTS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id     INT,
  invoice_number VARCHAR(50),
  client_name    VARCHAR(200),
  amount         DECIMAL(14,2) NOT NULL,
  payment_date   DATE,
  method         VARCHAR(100),
  transaction_id VARCHAR(200),
  status         ENUM('Success','Pending','Failed') DEFAULT 'Success',
  notes          TEXT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ACTIVITY LOG ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  action      VARCHAR(100),
  entity_type VARCHAR(50),
  entity_id   INT,
  details     TEXT,
  ip_address  VARCHAR(45),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTE ─────────────────────────────────────────────────────────
-- After running this SQL, go to: http://inv.optms.co.in/setup.php
-- The setup wizard will create your admin user with a correct password hash.

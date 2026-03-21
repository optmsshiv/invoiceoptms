-- ═══════════════════════════════════════════════════════
--  OPTMS Tech Invoice Manager — Database Schema
--  MySQL 8.0+ compatible
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS optms_invoice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE optms_invoice;

-- ── USERS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','staff') DEFAULT 'admin',
  avatar      TEXT,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── COMPANY SETTINGS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  `key`       VARCHAR(100) NOT NULL UNIQUE,
  value       TEXT,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── CLIENTS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clients (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200) NOT NULL,
  person      VARCHAR(150),
  email       VARCHAR(150),
  phone       VARCHAR(30),
  whatsapp    VARCHAR(30),
  gst_number  VARCHAR(20),
  address     TEXT,
  color       VARCHAR(10) DEFAULT '#00897B',
  logo        TEXT,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── PRODUCTS / SERVICES ────────────────────────────────
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
);

-- ── INVOICES ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoices (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number  VARCHAR(50) NOT NULL UNIQUE,
  client_id       INT,
  client_name     VARCHAR(200),
  service_type    VARCHAR(100),
  issued_date     DATE,
  due_date        DATE,
  status          ENUM('Draft','Pending','Paid','Overdue','Cancelled') DEFAULT 'Draft',
  currency        VARCHAR(5) DEFAULT '₹',
  subtotal        DECIMAL(14,2) DEFAULT 0,
  discount_pct    DECIMAL(5,2) DEFAULT 0,
  discount_amt    DECIMAL(12,2) DEFAULT 0,
  gst_amount      DECIMAL(12,2) DEFAULT 0,
  grand_total     DECIMAL(14,2) DEFAULT 0,
  notes           TEXT,
  bank_details    TEXT,
  terms           TEXT,
  company_logo    TEXT,
  client_logo     TEXT,
  signature       TEXT,
  qr_code         TEXT,
  template_id     TINYINT DEFAULT 1,
  generated_by    VARCHAR(200) DEFAULT 'OPTMS Tech Invoice Manager',
  show_generated  TINYINT(1) DEFAULT 1,
  pdf_options     JSON,
  created_by      INT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── INVOICE ITEMS ──────────────────────────────────────
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
);

-- ── PAYMENTS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id      INT,
  invoice_number  VARCHAR(50),
  client_name     VARCHAR(200),
  amount          DECIMAL(14,2) NOT NULL,
  payment_date    DATE,
  method          VARCHAR(100),
  transaction_id  VARCHAR(200),
  status          ENUM('Success','Pending','Failed') DEFAULT 'Success',
  notes           TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
);

-- ── ACTIVITY LOG ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  action      VARCHAR(100),
  entity_type VARCHAR(50),
  entity_id   INT,
  details     TEXT,
  ip_address  VARCHAR(45),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── DEFAULT DATA ──────────────────────────────────────
INSERT INTO users (name, email, password, role) VALUES
('Admin Kumar', 'admin@optmstech.in', '$2y$12$tGNqH8z2vD7AX3KmP1wMHuX4Q5Ry6TL9EwJ2bN0sC8fV3dIoYkEuO', 'admin');
-- Default password: Admin@1234  (bcrypt hash above — change after first login)

INSERT INTO settings (`key`, value) VALUES
('company_name',   'OPTMS Tech'),
('company_gst',    '22AAAAA0000A1Z5'),
('company_phone',  '+91 98765 43210'),
('company_email',  'optmstech@gmail.com'),
('company_website','www.optmstech.in'),
('company_address','Patna, Bihar, India – 800001'),
('company_upi',    'optmstech@upi'),
('invoice_prefix', 'OT-2025-'),
('default_gst',    '18'),
('due_days',       '15'),
('active_template','1'),
('company_logo',   ''),
('company_sign',   '');

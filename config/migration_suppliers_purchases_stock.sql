-- ═══════════════════════════════════════════════════════════
-- Migration: Suppliers + Purchases + Stock Ledger
-- Run this once against your existing invoice-manager database
-- ═══════════════════════════════════════════════════════════

-- 1. SUPPLIERS (buying-side "clients")
CREATE TABLE IF NOT EXISTS suppliers (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  contact_person VARCHAR(150) DEFAULT '',
  phone          VARCHAR(30)  DEFAULT '',
  email          VARCHAR(150) DEFAULT '',
  gst_number     VARCHAR(20)  DEFAULT '',
  country        VARCHAR(80)  DEFAULT 'India',
  address        TEXT,
  payment_terms  VARCHAR(100) DEFAULT '',
  opening_balance DECIMAL(12,2) DEFAULT 0,
  notes          TEXT,
  status         ENUM('active','archived') DEFAULT 'active',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. PURCHASES (purchase bill header — buying from a supplier)
CREATE TABLE IF NOT EXISTS purchases (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  purchase_no    VARCHAR(50)  NOT NULL,
  supplier_id    INT NOT NULL,
  supplier_invoice_ref VARCHAR(100) DEFAULT '',
  purchase_date  DATE NOT NULL,
  currency       VARCHAR(10)  DEFAULT 'INR',
  exchange_rate  DECIMAL(12,4) DEFAULT 1,
  subtotal       DECIMAL(14,2) DEFAULT 0,
  gst_amount     DECIMAL(14,2) DEFAULT 0,
  total          DECIMAL(14,2) DEFAULT 0,
  amount_paid    DECIMAL(14,2) DEFAULT 0,
  status         ENUM('Pending','Received','Partial','Paid') DEFAULT 'Pending',
  notes          TEXT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. PURCHASE ITEMS (line items per purchase — drives stock IN)
CREATE TABLE IF NOT EXISTS purchase_items (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id    INT NOT NULL,
  product_id     INT DEFAULT NULL,
  description    VARCHAR(255) NOT NULL,
  hsn            VARCHAR(20)  DEFAULT '',
  qty            DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit           VARCHAR(20)  DEFAULT 'pcs',
  rate           DECIMAL(14,2) NOT NULL DEFAULT 0,
  gst_pct        DECIMAL(5,2) DEFAULT 0,
  amount         DECIMAL(14,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. STOCK LEDGER (running IN/OUT movement per product; source of truth for stock-on-hand)
CREATE TABLE IF NOT EXISTS stock_ledger (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  product_id     INT NOT NULL,
  ref_type       ENUM('purchase','sale','adjustment') NOT NULL,
  ref_id         INT DEFAULT NULL,
  direction      ENUM('in','out') NOT NULL,
  qty            DECIMAL(12,3) NOT NULL,
  rate           DECIMAL(14,2) DEFAULT 0,
  balance_after  DECIMAL(12,3) NOT NULL DEFAULT 0,
  movement_date  DATE NOT NULL,
  notes          VARCHAR(255) DEFAULT '',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Helpful index for fast "current stock per product" lookups
CREATE INDEX idx_stock_product_date ON stock_ledger (product_id, movement_date);

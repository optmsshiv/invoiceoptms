-- ═══════════════════════════════════════════════════════════
-- Migration: Add Product to Stock (Stock In) — manual multi-product
-- stock inward entry, separate from Purchases (no GST/payment — pure
-- stock movement with batch tracking and weighbridge reference).
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS stock_in_entries (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  reference_no      VARCHAR(50) NOT NULL,
  reference_date    DATE NOT NULL,
  warehouse         VARCHAR(100) DEFAULT "Main Warehouse",
  stock_in_type     VARCHAR(30) DEFAULT "Purchase",
  remarks           TEXT,

  weighing_type       VARCHAR(30) DEFAULT "Own Weighbridge",
  weighbridge_name     VARCHAR(150) DEFAULT "",
  weighbridge_slip_no VARCHAR(50) DEFAULT "",
  weight_datetime     DATETIME DEFAULT NULL,
  gross_weight        DECIMAL(12,2) DEFAULT 0,
  tare_weight         DECIMAL(12,2) DEFAULT 0,
  operator_name       VARCHAR(100) DEFAULT "",
  slip_path           VARCHAR(255) DEFAULT "",

  supplier_id       INT DEFAULT NULL,
  challan_no        VARCHAR(50) DEFAULT "",
  challan_date      DATE DEFAULT NULL,
  vehicle_no        VARCHAR(30) DEFAULT "",
  driver_name       VARCHAR(100) DEFAULT "",

  attachments       TEXT,
  total_quantity    DECIMAL(12,3) DEFAULT 0,
  total_amount      DECIMAL(14,2) DEFAULT 0,

  created_by        INT DEFAULT NULL,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_in_items (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  stock_in_id    INT NOT NULL,
  product_id     INT NOT NULL,
  variety_grade  VARCHAR(100) DEFAULT "",
  batch_no       VARCHAR(50) DEFAULT "",
  mfg_date       DATE DEFAULT NULL,
  expiry_date    DATE DEFAULT NULL,
  qty            DECIMAL(12,3) NOT NULL DEFAULT 0,
  rate           DECIMAL(14,2) DEFAULT 0,
  amount         DECIMAL(14,2) DEFAULT 0,
  FOREIGN KEY (stock_in_id) REFERENCES stock_in_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

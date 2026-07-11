-- ═══════════════════════════════════════════════════════════
-- Migration: Stock Adjustment / Moisture Adjustment (rich, dedicated form)
-- Separate from the simple stock_ledger "adjustment" entries already
-- supported by api/stock.php — this captures the full audit trail
-- (batch, moisture before/after, approval) while still writing the
-- actual quantity change to stock_ledger so current-stock stays accurate.
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS stock_adjustments (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  adjustment_no      VARCHAR(50) NOT NULL,
  adjustment_date    DATE NOT NULL,
  adjustment_type    VARCHAR(30) DEFAULT "Moisture Loss",
  warehouse          VARCHAR(100) DEFAULT "Main Warehouse",
  reference_no       VARCHAR(100) DEFAULT "",
  reference_date     DATE DEFAULT NULL,

  product_id         INT NOT NULL,
  variety_grade      VARCHAR(100) DEFAULT "",
  grade              VARCHAR(50) DEFAULT "",
  unit               VARCHAR(20) DEFAULT "Kg",
  batch_no           VARCHAR(50) DEFAULT "",
  manufacture_date   DATE DEFAULT NULL,
  expiry_date        DATE DEFAULT NULL,
  supplier_id        INT DEFAULT NULL,

  opening_stock      DECIMAL(12,3) NOT NULL DEFAULT 0,
  moisture_before_pct DECIMAL(5,2) DEFAULT NULL,
  moisture_after_pct  DECIMAL(5,2) DEFAULT NULL,
  moisture_loss_pct   DECIMAL(5,2) DEFAULT NULL,
  weight_loss_kg      DECIMAL(12,3) NOT NULL DEFAULT 0,
  final_stock         DECIMAL(12,3) NOT NULL DEFAULT 0,

  reason             VARCHAR(100) DEFAULT "",
  remarks            TEXT,
  attachment_path    VARCHAR(255) DEFAULT "",
  approved_by        VARCHAR(100) DEFAULT "",
  approval_date      DATE DEFAULT NULL,
  notes              TEXT,

  created_by         INT DEFAULT NULL,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_stockadj_product_date ON stock_adjustments (product_id, adjustment_date);

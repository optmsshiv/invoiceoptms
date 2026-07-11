-- ═══════════════════════════════════════════════════════════
-- Safety migration for products table
-- Safe to run even if one/both columns already exist — checks first.
-- ═══════════════════════════════════════════════════════════

-- Add `hsn` if it doesn't already exist (the "Unknown column p.hsn" error
-- suggests this column was never actually created on your live DB)
SET @dbname = DATABASE();
SET @exists_hsn = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = @dbname AND table_name = 'products' AND column_name = 'hsn'
);
SET @sql_hsn = IF(@exists_hsn = 0,
  'ALTER TABLE products ADD COLUMN hsn VARCHAR(20) DEFAULT ""',
  'SELECT "hsn already exists, skipped"');
PREPARE stmt_hsn FROM @sql_hsn;
EXECUTE stmt_hsn;
DEALLOCATE PREPARE stmt_hsn;

-- Add `unit_family` if it doesn't already exist (from the unit-conversion migration —
-- safe to re-run this here too in case that migration wasn't applied yet)
SET @exists_uf = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = @dbname AND table_name = 'products' AND column_name = 'unit_family'
);
SET @sql_uf = IF(@exists_uf = 0,
  'ALTER TABLE products ADD COLUMN unit_family ENUM("count","weight","volume") NOT NULL DEFAULT "count"',
  'SELECT "unit_family already exists, skipped"');
PREPARE stmt_uf FROM @sql_uf;
EXECUTE stmt_uf;
DEALLOCATE PREPARE stmt_uf;

-- Add `status` (active/archived) if it doesn't already exist — needed for the
-- archive/restore feature already used on the Services/Products page
SET @exists_status = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = @dbname AND table_name = 'products' AND column_name = 'status'
);
SET @sql_status = IF(@exists_status = 0,
  'ALTER TABLE products ADD COLUMN status ENUM("active","archived") NOT NULL DEFAULT "active"',
  'SELECT "status already exists, skipped"');
PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;

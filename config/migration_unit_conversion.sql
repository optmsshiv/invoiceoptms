-- ═══════════════════════════════════════════════════════════
-- Migration: Unit-aware quantities (weight/volume/count)
-- Run this once, after the suppliers/purchases/stock migration
-- ═══════════════════════════════════════════════════════════

-- Each product declares what KIND of quantity it's measured in.
-- This decides which units are offered on purchase lines and what
-- the Stock Ledger's numbers actually mean for that product:
--   'count'  → tracked in pcs   (default — unchanged behavior for existing products)
--   'weight' → tracked in kg    (purchase lines can be entered in g or kg, auto-converted)
--   'volume' → tracked in ltr   (purchase lines can be entered in ml or ltr, auto-converted)
ALTER TABLE products
  ADD COLUMN unit_family ENUM('count','weight','volume') NOT NULL DEFAULT 'count' AFTER category;

-- Purchase lines keep BOTH the number as typed (matches the supplier's paper invoice
-- exactly, e.g. "500 g") AND the normalized value in the product's base unit
-- (what Stock Ledger actually uses, e.g. "0.5 kg") — qty/unit already store the
-- normalized value; these two new columns preserve what was actually typed.
ALTER TABLE purchase_items
  ADD COLUMN entered_qty  DECIMAL(12,3) DEFAULT NULL AFTER qty,
  ADD COLUMN entered_unit VARCHAR(10)   DEFAULT NULL AFTER entered_qty;

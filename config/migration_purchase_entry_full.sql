-- ═══════════════════════════════════════════════════════════
-- Migration: Full Purchase Entry page (weight-based grain/spice trading)
-- Adds columns to suppliers, purchases, purchase_items.
-- Safe to re-run — every block checks first, no CREATE PROCEDURE needed.
-- Tested against MySQL 5.7 / Percona Server 5.7 syntax.
-- ═══════════════════════════════════════════════════════════

SET @v0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='supplier_type');
SET @sv0 = IF(@v0=0, 'ALTER TABLE suppliers ADD COLUMN supplier_type VARCHAR(30) DEFAULT "Trader"', 'SELECT 1');
PREPARE stv0 FROM @sv0; EXECUTE stv0; DEALLOCATE PREPARE stv0;

SET @v1 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='state');
SET @sv1 = IF(@v1=0, 'ALTER TABLE suppliers ADD COLUMN state VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv1 FROM @sv1; EXECUTE stv1; DEALLOCATE PREPARE stv1;

SET @v2 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='district');
SET @sv2 = IF(@v2=0, 'ALTER TABLE suppliers ADD COLUMN district VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv2 FROM @sv2; EXECUTE stv2; DEALLOCATE PREPARE stv2;

SET @v3 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='reference_po_no');
SET @sv3 = IF(@v3=0, 'ALTER TABLE purchases ADD COLUMN reference_po_no VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv3 FROM @sv3; EXECUTE stv3; DEALLOCATE PREPARE stv3;

SET @v4 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='supplier_type');
SET @sv4 = IF(@v4=0, 'ALTER TABLE purchases ADD COLUMN supplier_type VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv4 FROM @sv4; EXECUTE stv4; DEALLOCATE PREPARE stv4;

SET @v5 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='gst_applicable');
SET @sv5 = IF(@v5=0, 'ALTER TABLE purchases ADD COLUMN gst_applicable TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stv5 FROM @sv5; EXECUTE stv5; DEALLOCATE PREPARE stv5;

SET @v6 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='supply_type');
SET @sv6 = IF(@v6=0, 'ALTER TABLE purchases ADD COLUMN supply_type VARCHAR(20) DEFAULT "Intra-State"', 'SELECT 1');
PREPARE stv6 FROM @sv6; EXECUTE stv6; DEALLOCATE PREPARE stv6;

SET @v7 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='transport_mode');
SET @sv7 = IF(@v7=0, 'ALTER TABLE purchases ADD COLUMN transport_mode VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv7 FROM @sv7; EXECUTE stv7; DEALLOCATE PREPARE stv7;

SET @v8 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='vehicle_no');
SET @sv8 = IF(@v8=0, 'ALTER TABLE purchases ADD COLUMN vehicle_no VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv8 FROM @sv8; EXECUTE stv8; DEALLOCATE PREPARE stv8;

SET @v9 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='driver_name');
SET @sv9 = IF(@v9=0, 'ALTER TABLE purchases ADD COLUMN driver_name VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv9 FROM @sv9; EXECUTE stv9; DEALLOCATE PREPARE stv9;

SET @v10 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='warehouse');
SET @sv10 = IF(@v10=0, 'ALTER TABLE purchases ADD COLUMN warehouse VARCHAR(100) DEFAULT "Main Warehouse"', 'SELECT 1');
PREPARE stv10 FROM @sv10; EXECUTE stv10; DEALLOCATE PREPARE stv10;

SET @v11 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='payment_terms');
SET @sv11 = IF(@v11=0, 'ALTER TABLE purchases ADD COLUMN payment_terms VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv11 FROM @sv11; EXECUTE stv11; DEALLOCATE PREPARE stv11;

SET @v12 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='payment_type');
SET @sv12 = IF(@v12=0, 'ALTER TABLE purchases ADD COLUMN payment_type VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv12 FROM @sv12; EXECUTE stv12; DEALLOCATE PREPARE stv12;

SET @v13 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='remarks');
SET @sv13 = IF(@v13=0, 'ALTER TABLE purchases ADD COLUMN remarks VARCHAR(255) DEFAULT ""', 'SELECT 1');
PREPARE stv13 FROM @sv13; EXECUTE stv13; DEALLOCATE PREPARE stv13;

SET @v14 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='transport_charge');
SET @sv14 = IF(@v14=0, 'ALTER TABLE purchases ADD COLUMN transport_charge DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv14 FROM @sv14; EXECUTE stv14; DEALLOCATE PREPARE stv14;

SET @v15 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='loading_charge');
SET @sv15 = IF(@v15=0, 'ALTER TABLE purchases ADD COLUMN loading_charge DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv15 FROM @sv15; EXECUTE stv15; DEALLOCATE PREPARE stv15;

SET @v16 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='packing_charge');
SET @sv16 = IF(@v16=0, 'ALTER TABLE purchases ADD COLUMN packing_charge DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv16 FROM @sv16; EXECUTE stv16; DEALLOCATE PREPARE stv16;

SET @v17 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='other_charges');
SET @sv17 = IF(@v17=0, 'ALTER TABLE purchases ADD COLUMN other_charges DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv17 FROM @sv17; EXECUTE stv17; DEALLOCATE PREPARE stv17;

SET @v18 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='discount_amount');
SET @sv18 = IF(@v18=0, 'ALTER TABLE purchases ADD COLUMN discount_amount DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv18 FROM @sv18; EXECUTE stv18; DEALLOCATE PREPARE stv18;

SET @v19 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='attachment_path');
SET @sv19 = IF(@v19=0, 'ALTER TABLE purchases ADD COLUMN attachment_path VARCHAR(255) DEFAULT ""', 'SELECT 1');
PREPARE stv19 FROM @sv19; EXECUTE stv19; DEALLOCATE PREPARE stv19;

SET @v20 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='payment_mode');
SET @sv20 = IF(@v20=0, 'ALTER TABLE purchases ADD COLUMN payment_mode VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv20 FROM @sv20; EXECUTE stv20; DEALLOCATE PREPARE stv20;

SET @v21 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='transaction_no');
SET @sv21 = IF(@v21=0, 'ALTER TABLE purchases ADD COLUMN transaction_no VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv21 FROM @sv21; EXECUTE stv21; DEALLOCATE PREPARE stv21;

SET @v22 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='payment_date');
SET @sv22 = IF(@v22=0, 'ALTER TABLE purchases ADD COLUMN payment_date DATE DEFAULT NULL', 'SELECT 1');
PREPARE stv22 FROM @sv22; EXECUTE stv22; DEALLOCATE PREPARE stv22;

SET @v23 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='gst_pct');
SET @sv23 = IF(@v23=0, 'ALTER TABLE purchases ADD COLUMN gst_pct DECIMAL(5,2) DEFAULT 0', 'SELECT 1');
PREPARE stv23 FROM @sv23; EXECUTE stv23; DEALLOCATE PREPARE stv23;

SET @v24 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='variety_grade');
SET @sv24 = IF(@v24=0, 'ALTER TABLE purchase_items ADD COLUMN variety_grade VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv24 FROM @sv24; EXECUTE stv24; DEALLOCATE PREPARE stv24;

SET @v25 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='moisture_pct');
SET @sv25 = IF(@v25=0, 'ALTER TABLE purchase_items ADD COLUMN moisture_pct DECIMAL(5,2) DEFAULT 0', 'SELECT 1');
PREPARE stv25 FROM @sv25; EXECUTE stv25; DEALLOCATE PREPARE stv25;

SET @v26 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='quality_grade');
SET @sv26 = IF(@v26=0, 'ALTER TABLE purchase_items ADD COLUMN quality_grade VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv26 FROM @sv26; EXECUTE stv26; DEALLOCATE PREPARE stv26;

SET @v27 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='gross_weight');
SET @sv27 = IF(@v27=0, 'ALTER TABLE purchase_items ADD COLUMN gross_weight DECIMAL(12,3) DEFAULT 0', 'SELECT 1');
PREPARE stv27 FROM @sv27; EXECUTE stv27; DEALLOCATE PREPARE stv27;

SET @v28 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='tare_weight');
SET @sv28 = IF(@v28=0, 'ALTER TABLE purchase_items ADD COLUMN tare_weight DECIMAL(12,3) DEFAULT 0', 'SELECT 1');
PREPARE stv28 FROM @sv28; EXECUTE stv28; DEALLOCATE PREPARE stv28;

SET @v29 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='dhalta_pct');
SET @sv29 = IF(@v29=0, 'ALTER TABLE purchase_items ADD COLUMN dhalta_pct DECIMAL(5,2) DEFAULT 0', 'SELECT 1');
PREPARE stv29 FROM @sv29; EXECUTE stv29; DEALLOCATE PREPARE stv29;

SET @v30 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='dhalta_kg');
SET @sv30 = IF(@v30=0, 'ALTER TABLE purchase_items ADD COLUMN dhalta_kg DECIMAL(12,3) DEFAULT 0', 'SELECT 1');
PREPARE stv30 FROM @sv30; EXECUTE stv30; DEALLOCATE PREPARE stv30;

SET @v31 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='billable_weight');
SET @sv31 = IF(@v31=0, 'ALTER TABLE purchase_items ADD COLUMN billable_weight DECIMAL(12,3) DEFAULT 0', 'SELECT 1');
PREPARE stv31 FROM @sv31; EXECUTE stv31; DEALLOCATE PREPARE stv31;

SET @v32 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchase_items' AND column_name='discount_pct');
SET @sv32 = IF(@v32=0, 'ALTER TABLE purchase_items ADD COLUMN discount_pct DECIMAL(5,2) DEFAULT 0', 'SELECT 1');
PREPARE stv32 FROM @sv32; EXECUTE stv32; DEALLOCATE PREPARE stv32;

-- ═══════════════════════════════════════════════════════════
-- Migration: Weight / Measurement Details on sales
-- Safe to re-run — checks first.
-- ═══════════════════════════════════════════════════════════

SET @v0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='weighing_type');
SET @sv0 = IF(@v0=0, 'ALTER TABLE sales ADD COLUMN weighing_type VARCHAR(30) DEFAULT "Dharam Kanta"', 'SELECT 1');
PREPARE stv0 FROM @sv0; EXECUTE stv0; DEALLOCATE PREPARE stv0;

SET @v1 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='kanta_name');
SET @sv1 = IF(@v1=0, 'ALTER TABLE sales ADD COLUMN kanta_name VARCHAR(150) DEFAULT ""', 'SELECT 1');
PREPARE stv1 FROM @sv1; EXECUTE stv1; DEALLOCATE PREPARE stv1;

SET @v2 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='weighbridge_slip_no');
SET @sv2 = IF(@v2=0, 'ALTER TABLE sales ADD COLUMN weighbridge_slip_no VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv2 FROM @sv2; EXECUTE stv2; DEALLOCATE PREPARE stv2;

SET @v3 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='weight_datetime');
SET @sv3 = IF(@v3=0, 'ALTER TABLE sales ADD COLUMN weight_datetime DATETIME DEFAULT NULL', 'SELECT 1');
PREPARE stv3 FROM @sv3; EXECUTE stv3; DEALLOCATE PREPARE stv3;

SET @v4 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='kanta_operator_name');
SET @sv4 = IF(@v4=0, 'ALTER TABLE sales ADD COLUMN kanta_operator_name VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv4 FROM @sv4; EXECUTE stv4; DEALLOCATE PREPARE stv4;

SET @v5 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='kanta_gross_weight');
SET @sv5 = IF(@v5=0, 'ALTER TABLE sales ADD COLUMN kanta_gross_weight DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv5 FROM @sv5; EXECUTE stv5; DEALLOCATE PREPARE stv5;

SET @v6 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='kanta_tare_weight');
SET @sv6 = IF(@v6=0, 'ALTER TABLE sales ADD COLUMN kanta_tare_weight DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv6 FROM @sv6; EXECUTE stv6; DEALLOCATE PREPARE stv6;

SET @v7 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='kanta_moisture_pct');
SET @sv7 = IF(@v7=0, 'ALTER TABLE sales ADD COLUMN kanta_moisture_pct DECIMAL(5,2) DEFAULT NULL', 'SELECT 1');
PREPARE stv7 FROM @sv7; EXECUTE stv7; DEALLOCATE PREPARE stv7;

SET @v8 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='kanta_dhalta_kg');
SET @sv8 = IF(@v8=0, 'ALTER TABLE sales ADD COLUMN kanta_dhalta_kg DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv8 FROM @sv8; EXECUTE stv8; DEALLOCATE PREPARE stv8;

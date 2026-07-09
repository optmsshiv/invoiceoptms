-- ═══════════════════════════════════════════════════════════
-- Migration: Full Add Supplier / Farmer page
-- Adds 19 columns to suppliers. Safe to re-run — checks first.
-- ═══════════════════════════════════════════════════════════

SET @v0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='email');
SET @sv0 = IF(@v0=0, 'ALTER TABLE suppliers ADD COLUMN email VARCHAR(150) DEFAULT ""', 'SELECT 1');
PREPARE stv0 FROM @sv0; EXECUTE stv0; DEALLOCATE PREPARE stv0;

SET @v1 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='date_of_registration');
SET @sv1 = IF(@v1=0, 'ALTER TABLE suppliers ADD COLUMN date_of_registration DATE DEFAULT NULL', 'SELECT 1');
PREPARE stv1 FROM @sv1; EXECUTE stv1; DEALLOCATE PREPARE stv1;

SET @v2 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='business_nature');
SET @sv2 = IF(@v2=0, 'ALTER TABLE suppliers ADD COLUMN business_nature VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv2 FROM @sv2; EXECUTE stv2; DEALLOCATE PREPARE stv2;

SET @v3 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='website');
SET @sv3 = IF(@v3=0, 'ALTER TABLE suppliers ADD COLUMN website VARCHAR(150) DEFAULT ""', 'SELECT 1');
PREPARE stv3 FROM @sv3; EXECUTE stv3; DEALLOCATE PREPARE stv3;

SET @v4 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='city');
SET @sv4 = IF(@v4=0, 'ALTER TABLE suppliers ADD COLUMN city VARCHAR(80) DEFAULT ""', 'SELECT 1');
PREPARE stv4 FROM @sv4; EXECUTE stv4; DEALLOCATE PREPARE stv4;

SET @v5 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='pincode');
SET @sv5 = IF(@v5=0, 'ALTER TABLE suppliers ADD COLUMN pincode VARCHAR(12) DEFAULT ""', 'SELECT 1');
PREPARE stv5 FROM @sv5; EXECUTE stv5; DEALLOCATE PREPARE stv5;

SET @v6 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='pan_no');
SET @sv6 = IF(@v6=0, 'ALTER TABLE suppliers ADD COLUMN pan_no VARCHAR(20) DEFAULT ""', 'SELECT 1');
PREPARE stv6 FROM @sv6; EXECUTE stv6; DEALLOCATE PREPARE stv6;

SET @v7 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='aadhaar_no');
SET @sv7 = IF(@v7=0, 'ALTER TABLE suppliers ADD COLUMN aadhaar_no VARCHAR(20) DEFAULT ""', 'SELECT 1');
PREPARE stv7 FROM @sv7; EXECUTE stv7; DEALLOCATE PREPARE stv7;

SET @v8 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='state_code');
SET @sv8 = IF(@v8=0, 'ALTER TABLE suppliers ADD COLUMN state_code VARCHAR(10) DEFAULT ""', 'SELECT 1');
PREPARE stv8 FROM @sv8; EXECUTE stv8; DEALLOCATE PREPARE stv8;

SET @v9 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='tan_no');
SET @sv9 = IF(@v9=0, 'ALTER TABLE suppliers ADD COLUMN tan_no VARCHAR(20) DEFAULT ""', 'SELECT 1');
PREPARE stv9 FROM @sv9; EXECUTE stv9; DEALLOCATE PREPARE stv9;

SET @v10 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='msme_no');
SET @sv10 = IF(@v10=0, 'ALTER TABLE suppliers ADD COLUMN msme_no VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv10 FROM @sv10; EXECUTE stv10; DEALLOCATE PREPARE stv10;

SET @v11 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='fssai_no');
SET @sv11 = IF(@v11=0, 'ALTER TABLE suppliers ADD COLUMN fssai_no VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv11 FROM @sv11; EXECUTE stv11; DEALLOCATE PREPARE stv11;

SET @v12 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='bank_name');
SET @sv12 = IF(@v12=0, 'ALTER TABLE suppliers ADD COLUMN bank_name VARCHAR(150) DEFAULT ""', 'SELECT 1');
PREPARE stv12 FROM @sv12; EXECUTE stv12; DEALLOCATE PREPARE stv12;

SET @v13 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='bank_account_no');
SET @sv13 = IF(@v13=0, 'ALTER TABLE suppliers ADD COLUMN bank_account_no VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv13 FROM @sv13; EXECUTE stv13; DEALLOCATE PREPARE stv13;

SET @v14 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='ifsc_code');
SET @sv14 = IF(@v14=0, 'ALTER TABLE suppliers ADD COLUMN ifsc_code VARCHAR(15) DEFAULT ""', 'SELECT 1');
PREPARE stv14 FROM @sv14; EXECUTE stv14; DEALLOCATE PREPARE stv14;

SET @v15 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='account_holder_name');
SET @sv15 = IF(@v15=0, 'ALTER TABLE suppliers ADD COLUMN account_holder_name VARCHAR(150) DEFAULT ""', 'SELECT 1');
PREPARE stv15 FROM @sv15; EXECUTE stv15; DEALLOCATE PREPARE stv15;

SET @v16 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='credit_limit');
SET @sv16 = IF(@v16=0, 'ALTER TABLE suppliers ADD COLUMN credit_limit DECIMAL(14,2) DEFAULT 0', 'SELECT 1');
PREPARE stv16 FROM @sv16; EXECUTE stv16; DEALLOCATE PREPARE stv16;

SET @v17 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='default_price_list');
SET @sv17 = IF(@v17=0, 'ALTER TABLE suppliers ADD COLUMN default_price_list VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv17 FROM @sv17; EXECUTE stv17; DEALLOCATE PREPARE stv17;

SET @v18 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='suppliers' AND column_name='documents');
SET @sv18 = IF(@v18=0, 'ALTER TABLE suppliers ADD COLUMN documents TEXT', 'SELECT 1');
PREPARE stv18 FROM @sv18; EXECUTE stv18; DEALLOCATE PREPARE stv18;

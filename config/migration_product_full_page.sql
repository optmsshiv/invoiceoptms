-- ═══════════════════════════════════════════════════════════
-- Migration: Full Add Product page (AgriTrade-style rich catalog)
-- Adds ~38 columns to products. Safe to re-run — checks first.
-- ═══════════════════════════════════════════════════════════

SET @v0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='sku');
SET @sv0 = IF(@v0=0, 'ALTER TABLE products ADD COLUMN sku VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv0 FROM @sv0; EXECUTE stv0; DEALLOCATE PREPARE stv0;

SET @v1 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='unit');
SET @sv1 = IF(@v1=0, 'ALTER TABLE products ADD COLUMN unit VARCHAR(20) DEFAULT "Kg"', 'SELECT 1');
PREPARE stv1 FROM @sv1; EXECUTE stv1; DEALLOCATE PREPARE stv1;

SET @v2 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='brand');
SET @sv2 = IF(@v2=0, 'ALTER TABLE products ADD COLUMN brand VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv2 FROM @sv2; EXECUTE stv2; DEALLOCATE PREPARE stv2;

SET @v3 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='variety');
SET @sv3 = IF(@v3=0, 'ALTER TABLE products ADD COLUMN variety VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv3 FROM @sv3; EXECUTE stv3; DEALLOCATE PREPARE stv3;

SET @v4 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='grade');
SET @sv4 = IF(@v4=0, 'ALTER TABLE products ADD COLUMN grade VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv4 FROM @sv4; EXECUTE stv4; DEALLOCATE PREPARE stv4;

SET @v5 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='barcode');
SET @sv5 = IF(@v5=0, 'ALTER TABLE products ADD COLUMN barcode VARCHAR(100) DEFAULT ""', 'SELECT 1');
PREPARE stv5 FROM @sv5; EXECUTE stv5; DEALLOCATE PREPARE stv5;

SET @v6 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='shelf_life_months');
SET @sv6 = IF(@v6=0, 'ALTER TABLE products ADD COLUMN shelf_life_months INT DEFAULT NULL', 'SELECT 1');
PREPARE stv6 FROM @sv6; EXECUTE stv6; DEALLOCATE PREPARE stv6;

SET @v7 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='storage_type');
SET @sv7 = IF(@v7=0, 'ALTER TABLE products ADD COLUMN storage_type VARCHAR(30) DEFAULT ""', 'SELECT 1');
PREPARE stv7 FROM @sv7; EXECUTE stv7; DEALLOCATE PREPARE stv7;

SET @v8 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='base_unit_label');
SET @sv8 = IF(@v8=0, 'ALTER TABLE products ADD COLUMN base_unit_label VARCHAR(20) DEFAULT "Kg"', 'SELECT 1');
PREPARE stv8 FROM @sv8; EXECUTE stv8; DEALLOCATE PREPARE stv8;

SET @v9 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='sale_unit');
SET @sv9 = IF(@v9=0, 'ALTER TABLE products ADD COLUMN sale_unit VARCHAR(20) DEFAULT "Kg"', 'SELECT 1');
PREPARE stv9 FROM @sv9; EXECUTE stv9; DEALLOCATE PREPARE stv9;

SET @v10 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='purchase_unit');
SET @sv10 = IF(@v10=0, 'ALTER TABLE products ADD COLUMN purchase_unit VARCHAR(20) DEFAULT "Kg"', 'SELECT 1');
PREPARE stv10 FROM @sv10; EXECUTE stv10; DEALLOCATE PREPARE stv10;

SET @v11 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='min_order_qty');
SET @sv11 = IF(@v11=0, 'ALTER TABLE products ADD COLUMN min_order_qty DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv11 FROM @sv11; EXECUTE stv11; DEALLOCATE PREPARE stv11;

SET @v12 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='moisture_limit');
SET @sv12 = IF(@v12=0, 'ALTER TABLE products ADD COLUMN moisture_limit DECIMAL(5,2) DEFAULT NULL', 'SELECT 1');
PREPARE stv12 FROM @sv12; EXECUTE stv12; DEALLOCATE PREPARE stv12;

SET @v13 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='foreign_matter_limit');
SET @sv13 = IF(@v13=0, 'ALTER TABLE products ADD COLUMN foreign_matter_limit DECIMAL(5,2) DEFAULT NULL', 'SELECT 1');
PREPARE stv13 FROM @sv13; EXECUTE stv13; DEALLOCATE PREPARE stv13;

SET @v14 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='broken_damage_limit');
SET @sv14 = IF(@v14=0, 'ALTER TABLE products ADD COLUMN broken_damage_limit DECIMAL(5,2) DEFAULT NULL', 'SELECT 1');
PREPARE stv14 FROM @sv14; EXECUTE stv14; DEALLOCATE PREPARE stv14;

SET @v15 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='oil_content');
SET @sv15 = IF(@v15=0, 'ALTER TABLE products ADD COLUMN oil_content DECIMAL(5,2) DEFAULT NULL', 'SELECT 1');
PREPARE stv15 FROM @sv15; EXECUTE stv15; DEALLOCATE PREPARE stv15;

SET @v16 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='admixture_limit');
SET @sv16 = IF(@v16=0, 'ALTER TABLE products ADD COLUMN admixture_limit DECIMAL(5,2) DEFAULT NULL', 'SELECT 1');
PREPARE stv16 FROM @sv16; EXECUTE stv16; DEALLOCATE PREPARE stv16;

SET @v17 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='color');
SET @sv17 = IF(@v17=0, 'ALTER TABLE products ADD COLUMN color VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv17 FROM @sv17; EXECUTE stv17; DEALLOCATE PREPARE stv17;

SET @v18 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='aroma');
SET @sv18 = IF(@v18=0, 'ALTER TABLE products ADD COLUMN aroma VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv18 FROM @sv18; EXECUTE stv18; DEALLOCATE PREPARE stv18;

SET @v19 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='shape_size');
SET @sv19 = IF(@v19=0, 'ALTER TABLE products ADD COLUMN shape_size VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv19 FROM @sv19; EXECUTE stv19; DEALLOCATE PREPARE stv19;

SET @v20 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='packing_type');
SET @sv20 = IF(@v20=0, 'ALTER TABLE products ADD COLUMN packing_type VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv20 FROM @sv20; EXECUTE stv20; DEALLOCATE PREPARE stv20;

SET @v21 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='packing_size');
SET @sv21 = IF(@v21=0, 'ALTER TABLE products ADD COLUMN packing_size VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv21 FROM @sv21; EXECUTE stv21; DEALLOCATE PREPARE stv21;

SET @v22 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='purchase_rate');
SET @sv22 = IF(@v22=0, 'ALTER TABLE products ADD COLUMN purchase_rate DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv22 FROM @sv22; EXECUTE stv22; DEALLOCATE PREPARE stv22;

SET @v23 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='sale_rate');
SET @sv23 = IF(@v23=0, 'ALTER TABLE products ADD COLUMN sale_rate DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv23 FROM @sv23; EXECUTE stv23; DEALLOCATE PREPARE stv23;

SET @v24 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='mrp');
SET @sv24 = IF(@v24=0, 'ALTER TABLE products ADD COLUMN mrp DECIMAL(12,2) DEFAULT 0', 'SELECT 1');
PREPARE stv24 FROM @sv24; EXECUTE stv24; DEALLOCATE PREPARE stv24;

SET @v25 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='tax_type');
SET @sv25 = IF(@v25=0, 'ALTER TABLE products ADD COLUMN tax_type VARCHAR(40) DEFAULT "Intra-State (CGST+SGST)"', 'SELECT 1');
PREPARE stv25 FROM @sv25; EXECUTE stv25; DEALLOCATE PREPARE stv25;

SET @v26 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='opening_stock');
SET @sv26 = IF(@v26=0, 'ALTER TABLE products ADD COLUMN opening_stock DECIMAL(12,3) DEFAULT 0', 'SELECT 1');
PREPARE stv26 FROM @sv26; EXECUTE stv26; DEALLOCATE PREPARE stv26;

SET @v27 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='reorder_level');
SET @sv27 = IF(@v27=0, 'ALTER TABLE products ADD COLUMN reorder_level DECIMAL(12,3) DEFAULT 0', 'SELECT 1');
PREPARE stv27 FROM @sv27; EXECUTE stv27; DEALLOCATE PREPARE stv27;

SET @v28 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='max_stock');
SET @sv28 = IF(@v28=0, 'ALTER TABLE products ADD COLUMN max_stock DECIMAL(12,3) DEFAULT 0', 'SELECT 1');
PREPARE stv28 FROM @sv28; EXECUTE stv28; DEALLOCATE PREPARE stv28;

SET @v29 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='default_warehouse');
SET @sv29 = IF(@v29=0, 'ALTER TABLE products ADD COLUMN default_warehouse VARCHAR(100) DEFAULT "Main Warehouse"', 'SELECT 1');
PREPARE stv29 FROM @sv29; EXECUTE stv29; DEALLOCATE PREPARE stv29;

SET @v30 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='track_batch');
SET @sv30 = IF(@v30=0, 'ALTER TABLE products ADD COLUMN track_batch TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stv30 FROM @sv30; EXECUTE stv30; DEALLOCATE PREPARE stv30;

SET @v31 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='track_serial');
SET @sv31 = IF(@v31=0, 'ALTER TABLE products ADD COLUMN track_serial TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stv31 FROM @sv31; EXECUTE stv31; DEALLOCATE PREPARE stv31;

SET @v32 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='short_description');
SET @sv32 = IF(@v32=0, 'ALTER TABLE products ADD COLUMN short_description VARCHAR(200) DEFAULT ""', 'SELECT 1');
PREPARE stv32 FROM @sv32; EXECUTE stv32; DEALLOCATE PREPARE stv32;

SET @v33 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='detailed_description');
SET @sv33 = IF(@v33=0, 'ALTER TABLE products ADD COLUMN detailed_description VARCHAR(500) DEFAULT ""', 'SELECT 1');
PREPARE stv33 FROM @sv33; EXECUTE stv33; DEALLOCATE PREPARE stv33;

SET @v34 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='country_of_origin');
SET @sv34 = IF(@v34=0, 'ALTER TABLE products ADD COLUMN country_of_origin VARCHAR(80) DEFAULT "India"', 'SELECT 1');
PREPARE stv34 FROM @sv34; EXECUTE stv34; DEALLOCATE PREPARE stv34;

SET @v35 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='manufacturer');
SET @sv35 = IF(@v35=0, 'ALTER TABLE products ADD COLUMN manufacturer VARCHAR(150) DEFAULT ""', 'SELECT 1');
PREPARE stv35 FROM @sv35; EXECUTE stv35; DEALLOCATE PREPARE stv35;

SET @v36 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='fssai_license');
SET @sv36 = IF(@v36=0, 'ALTER TABLE products ADD COLUMN fssai_license VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv36 FROM @sv36; EXECUTE stv36; DEALLOCATE PREPARE stv36;

SET @v37 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='iec_code');
SET @sv37 = IF(@v37=0, 'ALTER TABLE products ADD COLUMN iec_code VARCHAR(50) DEFAULT ""', 'SELECT 1');
PREPARE stv37 FROM @sv37; EXECUTE stv37; DEALLOCATE PREPARE stv37;

SET @v38 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='tags');
SET @sv38 = IF(@v38=0, 'ALTER TABLE products ADD COLUMN tags TEXT', 'SELECT 1');
PREPARE stv38 FROM @sv38; EXECUTE stv38; DEALLOCATE PREPARE stv38;

SET @v39 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='images');
SET @sv39 = IF(@v39=0, 'ALTER TABLE products ADD COLUMN images TEXT', 'SELECT 1');
PREPARE stv39 FROM @sv39; EXECUTE stv39; DEALLOCATE PREPARE stv39;

SET @v40 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='attachments');
SET @sv40 = IF(@v40=0, 'ALTER TABLE products ADD COLUMN attachments TEXT', 'SELECT 1');
PREPARE stv40 FROM @sv40; EXECUTE stv40; DEALLOCATE PREPARE stv40;

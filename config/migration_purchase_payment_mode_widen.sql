-- ═══════════════════════════════════════════════════════════
-- Migration: widen purchases.payment_mode
-- Root cause of "split amount truncated on edit": this column was
-- VARCHAR(30), but a composed split label like
-- "Split: Cash: ₹57551 + UPI: ₹20000" is 33+ characters — MySQL
-- silently truncated it on save, chopping the last method's amount.
-- Safe to re-run.
-- ═══════════════════════════════════════════════════════════
SET @v0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='purchases' AND column_name='payment_mode' AND character_maximum_length >= 255);
SET @s0 = IF(@v0=0, 'ALTER TABLE purchases MODIFY COLUMN payment_mode VARCHAR(255) DEFAULT ""', 'SELECT 1');
PREPARE st0 FROM @s0; EXECUTE st0; DEALLOCATE PREPARE st0;

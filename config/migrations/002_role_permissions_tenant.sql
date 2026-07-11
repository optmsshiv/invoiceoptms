-- ================================================================
--  Migration: role_permissions table (TENANT database)
--  Run this against EACH tenant database (the one with `invoices`,
--  `clients`, etc. — not the master DB).
--
--  Without this table/data, ANY user whose role isn't exactly
--  'owner' or 'super_admin' gets "Access Denied" on every single
--  page — requireLogin()/requirePermission() bypass this check
--  entirely for owner/super_admin, but every other role (admin,
--  manager, accountant, sales, viewer) depends on rows existing
--  here for each thing they're allowed to see.
--
--  Defaults below are intentionally generous (most things ON) so
--  nothing is unexpectedly blocked for your team — tighten specific
--  roles later from Team → role permissions (api/role_permissions.php)
--  rather than everyone starting locked out.
--
--  Safe to re-run: INSERT ... ON DUPLICATE KEY UPDATE throughout.
-- ================================================================

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `role`           VARCHAR(32) NOT NULL,
    `permission_key` VARCHAR(64) NOT NULL,
    `enabled`        TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `role_key` (`role`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── admin: everything except nothing — highest non-owner tier ─────
INSERT INTO `role_permissions` (`role`, `permission_key`, `enabled`) VALUES
('admin','menu.dashboard',1),('admin','menu.invoices',1),('admin','menu.create',1),
('admin','menu.clients',1),('admin','menu.products',1),('admin','menu.suppliers',1),
('admin','menu.purchases',1),('admin','menu.sales',1),('admin','menu.stock',1),
('admin','menu.payments',1),('admin','menu.credit_notes',1),('admin','menu.reports',1),
('admin','menu.aging',1),('admin','menu.expenses',1),('admin','menu.tax',1),
('admin','menu.reminders',1),('admin','menu.recurring',1),('admin','menu.portal',1),
('admin','menu.activity',1),('admin','menu.templates',1),('admin','menu.whatsapp',1),
('admin','menu.email_setup',1),('admin','menu.msglog',1),('admin','menu.team',1),
('admin','menu.settings',1)
ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`);

-- ── manager: everything operational + finance + oversight, but not
--    team/settings (those stay admin/owner) ────────────────────────
INSERT INTO `role_permissions` (`role`, `permission_key`, `enabled`) VALUES
('manager','menu.dashboard',1),('manager','menu.invoices',1),('manager','menu.create',1),
('manager','menu.clients',1),('manager','menu.products',1),('manager','menu.suppliers',1),
('manager','menu.purchases',1),('manager','menu.sales',1),('manager','menu.stock',1),
('manager','menu.payments',1),('manager','menu.credit_notes',1),('manager','menu.reports',1),
('manager','menu.aging',1),('manager','menu.expenses',1),('manager','menu.tax',1),
('manager','menu.reminders',1),('manager','menu.recurring',1),('manager','menu.portal',1),
('manager','menu.activity',1),('manager','menu.templates',0),('manager','menu.whatsapp',0),
('manager','menu.email_setup',0),('manager','menu.msglog',1),('manager','menu.team',0),
('manager','menu.settings',0)
ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`);

-- ── accountant: finance-focused + core operational, no comms/admin ─
INSERT INTO `role_permissions` (`role`, `permission_key`, `enabled`) VALUES
('accountant','menu.dashboard',1),('accountant','menu.invoices',1),('accountant','menu.create',1),
('accountant','menu.clients',1),('accountant','menu.products',1),('accountant','menu.suppliers',1),
('accountant','menu.purchases',1),('accountant','menu.sales',1),('accountant','menu.stock',1),
('accountant','menu.payments',1),('accountant','menu.credit_notes',1),('accountant','menu.reports',1),
('accountant','menu.aging',1),('accountant','menu.expenses',1),('accountant','menu.tax',1),
('accountant','menu.reminders',1),('accountant','menu.recurring',1),('accountant','menu.portal',0),
('accountant','menu.activity',0),('accountant','menu.templates',0),('accountant','menu.whatsapp',0),
('accountant','menu.email_setup',0),('accountant','menu.msglog',0),('accountant','menu.team',0),
('accountant','menu.settings',0)
ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`);

-- ── sales: day-to-day operational work, no finance reports/admin ──
INSERT INTO `role_permissions` (`role`, `permission_key`, `enabled`) VALUES
('sales','menu.dashboard',1),('sales','menu.invoices',1),('sales','menu.create',1),
('sales','menu.clients',1),('sales','menu.products',1),('sales','menu.suppliers',1),
('sales','menu.purchases',1),('sales','menu.sales',1),('sales','menu.stock',1),
('sales','menu.payments',1),('sales','menu.credit_notes',0),('sales','menu.reports',0),
('sales','menu.aging',0),('sales','menu.expenses',0),('sales','menu.tax',0),
('sales','menu.reminders',1),('sales','menu.recurring',1),('sales','menu.portal',1),
('sales','menu.activity',0),('sales','menu.templates',0),('sales','menu.whatsapp',0),
('sales','menu.email_setup',0),('sales','menu.msglog',0),('sales','menu.team',0),
('sales','menu.settings',0)
ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`);

-- ── viewer: read-only look around, no create/edit-heavy pages ─────
INSERT INTO `role_permissions` (`role`, `permission_key`, `enabled`) VALUES
('viewer','menu.dashboard',1),('viewer','menu.invoices',1),('viewer','menu.create',0),
('viewer','menu.clients',1),('viewer','menu.products',1),('viewer','menu.suppliers',1),
('viewer','menu.purchases',1),('viewer','menu.sales',1),('viewer','menu.stock',1),
('viewer','menu.payments',1),('viewer','menu.credit_notes',0),('viewer','menu.reports',1),
('viewer','menu.aging',0),('viewer','menu.expenses',0),('viewer','menu.tax',0),
('viewer','menu.reminders',0),('viewer','menu.recurring',0),('viewer','menu.portal',0),
('viewer','menu.activity',0),('viewer','menu.templates',0),('viewer','menu.whatsapp',0),
('viewer','menu.email_setup',0),('viewer','menu.msglog',0),('viewer','menu.team',0),
('viewer','menu.settings',0)
ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`);

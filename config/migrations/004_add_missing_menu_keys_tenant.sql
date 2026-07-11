-- ================================================================
--  Migration: role_permissions for the 4 missing keys (TENANT db)
--  Run against edrppymy_sneha_enterprises (and any other tenant DB)
--
--  Companion to 003_add_missing_menu_keys_master.sql — that adds the
--  keys to the master catalog; this grants them per role, matching
--  the pattern your own data already uses for the similar
--  menu.products key (admin/manager/accountant enabled, sales/viewer
--  off — tweak from Team → role permissions if you want it
--  different for these specific pages).
--
--  Safe to re-run: ON DUPLICATE KEY UPDATE via the existing
--  uk_role_perm unique constraint on (role, permission_key).
-- ================================================================

INSERT INTO `role_permissions` (`role`, `permission_key`, `enabled`) VALUES
('admin',      'menu.suppliers', 1),
('manager',    'menu.suppliers', 1),
('accountant', 'menu.suppliers', 1),
('sales',      'menu.suppliers', 0),
('viewer',     'menu.suppliers', 0),
('admin',      'menu.purchases', 1),
('manager',    'menu.purchases', 1),
('accountant', 'menu.purchases', 1),
('sales',      'menu.purchases', 0),
('viewer',     'menu.purchases', 0),
('admin',      'menu.sales', 1),
('manager',    'menu.sales', 1),
('accountant', 'menu.sales', 1),
('sales',      'menu.sales', 0),
('viewer',     'menu.sales', 0),
('admin',      'menu.stock', 1),
('manager',    'menu.stock', 1),
('accountant', 'menu.stock', 1),
('sales',      'menu.stock', 0),
('viewer',     'menu.stock', 0)
ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`);

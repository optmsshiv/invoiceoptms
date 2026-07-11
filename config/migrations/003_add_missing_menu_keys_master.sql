-- ================================================================
--  Migration: add the 4 missing permission keys (MASTER database)
--  Run against edrppymy_optms_master
--
--  Your permissions system is fully set up and working — the earlier
--  migration files I gave you were based on a wrong assumption
--  (that the tables were empty). They're not; this is the real,
--  narrow fix based on your actual database export.
--
--  The MPA's Purchases, Sales, Stock, and Suppliers pages check
--  permission keys (menu.purchases, menu.sales, menu.stock,
--  menu.suppliers) that don't exist in your `permissions` catalog at
--  all — not even the SPA used them, since the SPA didn't gate these
--  as separate pages the same way. Since getEffectivePermissions()
--  only ever sets a result for keys that exist in this catalog, these
--  4 pages show "Access Denied" for every role, including owner,
--  regardless of role_permissions or tenant overrides.
--
--  Safe to re-run: ON DUPLICATE KEY UPDATE via the existing uk_key
--  unique constraint on `key`.
-- ================================================================

INSERT INTO `permissions` (`key`, `label`, `category`, `default_min_role`, `sort_order`) VALUES
('menu.suppliers', 'Suppliers', 'menu', 'sales', 51),
('menu.purchases', 'Purchases', 'menu', 'sales', 52),
('menu.sales',     'Sales',     'menu', 'sales', 53),
('menu.stock',     'Stock Ledger', 'menu', 'sales', 54)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `category` = VALUES(`category`),
    `default_min_role` = VALUES(`default_min_role`),
    `sort_order` = VALUES(`sort_order`);

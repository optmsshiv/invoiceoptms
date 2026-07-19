-- ================================================================
-- add_menu_stock_history_permission.sql
--
-- Adds the new sidebar nav item "Stock History" (menu.stock_history)
-- to the permission system, so requirePermission('menu.stock_history')
-- in pages/stock-history.php and pages/stock-txn-details.php resolves
-- correctly instead of silently denying everyone.
--
-- IMPORTANT — verify before running:
-- The `permissions`, `plan_permissions`, and `tenant_permission_overrides`
-- tables are NOT in config/master_schema.sql (they weren't in the zip you
-- gave me), so I've inferred their columns only from how
-- includes/auth.php::getEffectivePermissions() queries them:
--   permissions(key)
--   plan_permissions(plan, permission_key, enabled)
--   role_permissions(role, permission_key, enabled)  -- lives in tenant DB
-- Please confirm actual column names against your live master DB
-- (e.g. `key` vs `permission_key`, extra columns like `label`/`category`)
-- before running this on production.
-- ================================================================

-- Run this against the MASTER database.

-- 1. Register the permission key in the master catalog.
INSERT IGNORE INTO permissions (`key`) VALUES ('menu.stock_history');

-- 2. Enable it by default for every existing plan (owner/super_admin bypass
--    this table entirely per getEffectivePermissions(), but other roles
--    need an explicit plan_permissions row or they'll default to false).
INSERT IGNORE INTO plan_permissions (plan, permission_key, enabled)
SELECT DISTINCT plan, 'menu.stock_history', 1 FROM plan_permissions;

-- 3. For each tenant DB, seed the role_permissions row so non-owner roles
--    (manager, accountant, sales, viewer) can see it if their role
--    already has menu.stock enabled. Run this once PER TENANT DATABASE
--    (adjust to match whatever role already has menu.stock = 1 in yours):
--
-- INSERT IGNORE INTO role_permissions (role, permission_key, enabled)
-- SELECT role, 'menu.stock_history', enabled
-- FROM role_permissions
-- WHERE permission_key = 'menu.stock';

-- ================================================================
-- add_menu_sales_customers_permissions.sql
--
-- Adds the "Sales" (menu.sales) and "Customers" (menu.customers) nav
-- items to the permission system for Phase 2. Same caveat as Phase 1's
-- migration: verify actual column names on your live master DB before
-- running — I inferred the table shape from includes/auth.php's queries,
-- not from a schema file (permissions/plan_permissions/
-- tenant_permission_overrides weren't in the zip you gave me).
--
-- Run against the MASTER database.
-- ================================================================

INSERT IGNORE INTO permissions (`key`) VALUES ('menu.sales');
INSERT IGNORE INTO permissions (`key`) VALUES ('menu.customers');

INSERT IGNORE INTO plan_permissions (plan, permission_key, enabled)
SELECT DISTINCT plan, 'menu.sales', 1 FROM plan_permissions;
INSERT IGNORE INTO plan_permissions (plan, permission_key, enabled)
SELECT DISTINCT plan, 'menu.customers', 1 FROM plan_permissions;

-- Per-tenant DB, seed role_permissions the same way as menu.stock_history
-- (adjust 'menu.invoices' to whatever existing key your roles already
-- have sensibly configured, if you want a different starting point):
--
-- INSERT IGNORE INTO role_permissions (role, permission_key, enabled)
-- SELECT role, 'menu.sales', enabled FROM role_permissions WHERE permission_key = 'menu.invoices';
-- INSERT IGNORE INTO role_permissions (role, permission_key, enabled)
-- SELECT role, 'menu.customers', enabled FROM role_permissions WHERE permission_key = 'menu.invoices';

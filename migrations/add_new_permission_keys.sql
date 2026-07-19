-- ================================================================
-- add_new_permission_keys.sql
--
-- CORRECTED VERSION — the previous migration files I gave you were
-- written against a guessed schema and would have failed outright
-- (the `permissions` table's `label` column is NOT NULL with no
-- default, and my earlier INSERTs never provided one). This version
-- is verified against your actual edrppymy_optms_master.sql dump.
--
-- Also corrected a wrong assumption: `menu.sales` and `menu.stock`
-- ALREADY EXIST in your permissions table (ids 32, 33) — only
-- `menu.customers` and `menu.stock_history` are genuinely new.
--
-- Run this against your MASTER database.
-- ================================================================

INSERT IGNORE INTO permissions (`key`, `label`, `category`, `default_min_role`, `sort_order`) VALUES
  ('menu.customers',     'Customers',      'menu', 'sales', 45),
  ('menu.stock_history', 'Stock History',  'menu', 'sales', 55);

-- Note on plan_permissions: NOT needed here. Your `getEffectivePermissions()`
-- treats an absent plan_permissions row as "allowed" by default
-- (`$planPerms[$key] ?? true`) — this is the same pattern already used
-- for most of your existing menu.* keys (menu.sales and menu.stock have
-- zero plan_permissions rows each, and they work fine). Only add rows
-- here if you want to explicitly RESTRICT one of these two on a
-- specific plan, e.g.:
--   INSERT INTO plan_permissions (plan, permission_key, enabled) VALUES ('basic', 'menu.customers', 0);

-- ================================================================
-- Per-tenant step (run against EACH TENANT database, not master):
--
-- Owner/super_admin roles bypass role_permissions entirely and will
-- see the new menu items immediately with no further action. Every
-- OTHER role (manager, accountant, sales, viewer) needs an explicit
-- row in that tenant's role_permissions table, or the item stays
-- hidden for them — there's no "?? true" fallback at this layer.
--
-- Adjust the source key below to whichever existing key you want to
-- copy each role's current on/off setting from. menu.sales is a
-- reasonable default since it's closely related:
-- ================================================================

INSERT IGNORE INTO role_permissions (role, permission_key, enabled)
SELECT role, 'menu.customers', enabled FROM role_permissions WHERE permission_key = 'menu.sales';

INSERT IGNORE INTO role_permissions (role, permission_key, enabled)
SELECT role, 'menu.stock_history', enabled FROM role_permissions WHERE permission_key = 'menu.stock';

-- If your tenant's role_permissions table has no row at all for
-- menu.sales/menu.stock either (possible, since those were also
-- recently added), the two INSERTs above will silently do nothing.
-- In that case, check what roles exist for this tenant and insert
-- directly, e.g.:
--   INSERT INTO role_permissions (role, permission_key, enabled) VALUES
--     ('manager', 'menu.customers', 1), ('accountant', 'menu.customers', 1),
--     ('sales', 'menu.customers', 1), ('viewer', 'menu.customers', 0);

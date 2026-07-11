-- ================================================================
--  Migration: permissions system tables (MASTER database)
--  Run this against your master DB (the one with `tenants`, `users`).
--
--  Fixes: "Access Denied" on every page. getEffectivePermissions()
--  in includes/auth.php queries these three tables — if they don't
--  exist, or `permissions` has no rows, every permission check comes
--  back empty/false for everyone (a fix was also added in code so
--  this fails open instead of locking you out entirely, but you
--  still want this real data in place for actual per-role control).
--
--  Safe to re-run: CREATE TABLE IF NOT EXISTS + INSERT ... ON
--  DUPLICATE KEY UPDATE throughout.
-- ================================================================

-- ── Permission catalog: every controllable menu/feature key ───────
CREATE TABLE IF NOT EXISTS `permissions` (
    `key`              VARCHAR(64) NOT NULL PRIMARY KEY,
    `label`            VARCHAR(128) NOT NULL,
    `category`         VARCHAR(64) NOT NULL DEFAULT 'General',
    `default_min_role` VARCHAR(32) NOT NULL DEFAULT 'viewer',
    `sort_order`       INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Per-plan feature gating (trial/basic/pro/enterprise) ───────────
-- No row for a given plan+key = allowed by default (see
-- getEffectivePermissions()'s `$planPerms[$key] ?? true`), so this
-- table only needs rows for things you want to actually RESTRICT on
-- a given plan. Left empty here — add rows via api/permissions.php's
-- UI (Super Admin) if/when you want to limit features per plan.
CREATE TABLE IF NOT EXISTS `plan_permissions` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `plan`           VARCHAR(32) NOT NULL,
    `permission_key` VARCHAR(64) NOT NULL,
    `enabled`        TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `plan_key` (`plan`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Per-tenant overrides (force a feature on/off for one tenant,
--    regardless of their plan) ──────────────────────────────────────
-- Also left empty — same reasoning, add rows via the UI as needed.
CREATE TABLE IF NOT EXISTS `tenant_permission_overrides` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`      INT NOT NULL,
    `permission_key` VARCHAR(64) NOT NULL,
    `enabled`        TINYINT(1) NOT NULL DEFAULT 1,
    `set_by`         INT NULL,
    UNIQUE KEY `tenant_key` (`tenant_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed the permission catalog with every menu.* key the app
--    actually checks via requirePermission() ────────────────────────
INSERT INTO `permissions` (`key`, `label`, `category`, `default_min_role`, `sort_order`) VALUES
('menu.dashboard',    'Dashboard',           'Core',           'viewer',     10),
('menu.invoices',     'Invoices (view)',     'Core',           'viewer',     20),
('menu.create',       'Create/Edit Invoice', 'Core',           'sales',      30),
('menu.clients',      'Clients',             'Core',           'sales',      40),
('menu.products',     'Products / Services', 'Core',           'sales',      50),
('menu.suppliers',    'Suppliers',           'Core',           'sales',      60),
('menu.purchases',    'Purchases',           'Core',           'sales',      70),
('menu.sales',        'Sales',               'Core',           'sales',      80),
('menu.stock',        'Stock Ledger',        'Core',           'sales',      90),
('menu.payments',     'Payments',            'Finance',        'accountant', 100),
('menu.credit_notes', 'Credit Notes',        'Finance',        'accountant', 110),
('menu.reports',      'Reports',             'Finance',        'accountant', 120),
('menu.aging',        'Aging Report',        'Finance',        'accountant', 130),
('menu.expenses',     'Expenses',            'Finance',        'accountant', 140),
('menu.tax',          'Tax Summary',         'Finance',        'accountant', 150),
('menu.reminders',    'Reminders',           'Tools',          'sales',      160),
('menu.recurring',    'Recurring Invoices',  'Tools',          'sales',      170),
('menu.portal',       'Client Portal',       'Tools',          'sales',      180),
('menu.activity',     'Activity Log',        'Tools',          'manager',    190),
('menu.templates',    'PDF Templates',       'Communications', 'admin',      200),
('menu.whatsapp',     'WhatsApp Setup',      'Communications', 'admin',      210),
('menu.email_setup',  'Email Setup',         'Communications', 'admin',      220),
('menu.msglog',       'Message Log',         'Communications', 'manager',    230),
('menu.team',         'Team Management',     'Admin',          'admin',      240),
('menu.settings',     'Settings',            'Admin',          'admin',      250)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `category` = VALUES(`category`),
    `default_min_role` = VALUES(`default_min_role`),
    `sort_order` = VALUES(`sort_order`);

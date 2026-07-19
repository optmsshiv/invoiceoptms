-- ================================================================
--  OPTMS Master Database Schema
--  Database: optms_master
--  Run this ONCE to set up the master database
-- ================================================================

CREATE DATABASE IF NOT EXISTS `optms_master`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `optms_master`;

-- ── Tenants ───────────────────────────────────────────────────────
-- One row per client company
CREATE TABLE IF NOT EXISTS `tenants` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(60)      NOT NULL COMMENT 'URL-safe identifier: acme, xyz_ltd',
  `company_name` VARCHAR(200)     NOT NULL,
  `db_name`      VARCHAR(100)     NOT NULL COMMENT 'optms_acme',
  `plan`         ENUM('trial','basic','pro','enterprise') NOT NULL DEFAULT 'trial',
  `status`       ENUM('active','suspended','cancelled')   NOT NULL DEFAULT 'active',
  `trial_ends`   DATE             NULL     DEFAULT NULL,
  `owner_email`  VARCHAR(200)     NOT NULL,
  `owner_name`   VARCHAR(200)     NOT NULL DEFAULT '',
  `phone`        VARCHAR(30)      NULL,
  `logo`         TEXT             NULL,
  `notes`        TEXT             NULL     COMMENT 'Internal admin notes',
  `created_by`   INT UNSIGNED     NULL     COMMENT 'super_admin user_id',
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug`    (`slug`),
  UNIQUE KEY `uk_db_name` (`db_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Users ─────────────────────────────────────────────────────────
-- All users across all tenants + super admins
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED    NULL     COMMENT 'NULL = super_admin',
  `name`          VARCHAR(200)    NOT NULL,
  `email`         VARCHAR(200)    NOT NULL,
  `password`      VARCHAR(255)    NOT NULL COMMENT 'bcrypt',
  `role`          ENUM(
                    'super_admin',
                    'owner',
                    'admin',
                    'manager',
                    'accountant',
                    'sales',
                    'viewer'
                  )               NOT NULL DEFAULT 'sales',
  `status`        ENUM('active','inactive','invited') NOT NULL DEFAULT 'invited',
  `avatar`        TEXT            NULL,
  `phone`         VARCHAR(30)     NULL,
  `invite_token`  VARCHAR(64)     NULL     COMMENT 'For email-based invite flow',
  `invite_expiry` DATETIME        NULL,
  `reset_token`   VARCHAR(64)     NULL     COMMENT 'Password reset token',
  `reset_expiry`  DATETIME        NULL,
  `last_login`    DATETIME        NULL,
  `login_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`    INT UNSIGNED    NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_role`   (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Plans (optional — for future subscription billing) ────────────
CREATE TABLE IF NOT EXISTS `plans` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(60)   NOT NULL,
  `slug`           VARCHAR(30)   NOT NULL,
  `max_users`      INT           NOT NULL DEFAULT 3,
  `max_invoices`   INT           NOT NULL DEFAULT 500   COMMENT '0 = unlimited',
  `max_clients`    INT           NOT NULL DEFAULT 100   COMMENT '0 = unlimited',
  `has_recurring`  TINYINT(1)    NOT NULL DEFAULT 1,
  `has_whatsapp`   TINYINT(1)    NOT NULL DEFAULT 1,
  `has_email`      TINYINT(1)    NOT NULL DEFAULT 1,
  `has_reports`    TINYINT(1)    NOT NULL DEFAULT 1,
  `price_monthly`  DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Master audit log ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `master_audit_log` (
  `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED     NULL,
  `tenant_id`  INT UNSIGNED     NULL,
  `action`     VARCHAR(100)     NOT NULL,
  `details`    TEXT             NULL,
  `ip`         VARCHAR(45)      NULL,
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed default plans ────────────────────────────────────────────
INSERT IGNORE INTO `plans` (`name`, `slug`, `max_users`, `max_invoices`, `max_clients`, `price_monthly`) VALUES
('Trial',      'trial',      2,   50,   20,   0.00),
('Basic',      'basic',      3,   500,  100,  499.00),
('Pro',        'pro',        10,  0,    0,    999.00),
('Enterprise', 'enterprise', 0,   0,    0,    1999.00);

-- ── Seed super admin user ─────────────────────────────────────────
-- Password: Admin@1234 (change immediately after setup)
INSERT IGNORE INTO `users`
  (`tenant_id`, `name`, `email`, `password`, `role`, `status`)
VALUES
  (NULL, 'Super Admin', 'superadmin@optmstech.in',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'super_admin', 'active');


-- ── Permission system ────────────────────────────────────────────
-- Matches the confirmed-working live schema exactly (verified against
-- an actual database export) — see config/migrations/003_*.sql for
-- the note on why 4 extra keys (menu.suppliers/purchases/sales/stock)
-- are seeded here beyond what the original SPA used.
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`               INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `key`              VARCHAR(80) NOT NULL,
    `label`            VARCHAR(150) NOT NULL,
    `category`         ENUM('menu','action') NOT NULL DEFAULT 'menu',
    `default_min_role` ENUM('viewer','sales','accountant','manager','admin','owner') NOT NULL DEFAULT 'viewer',
    `sort_order`       INT(11) NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plan_permissions` (
    `id`             INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plan`           ENUM('trial','basic','pro','enterprise') NOT NULL,
    `permission_key` VARCHAR(80) NOT NULL,
    `enabled`        TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_plan_key` (`plan`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_permission_overrides` (
    `id`             INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`      INT(10) UNSIGNED NOT NULL,
    `permission_key` VARCHAR(80) NOT NULL,
    `enabled`        TINYINT(1) NOT NULL COMMENT '1 = force on, 0 = force off',
    `set_by`         INT(10) UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_key` (`tenant_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permissions` (`key`, `label`, `category`, `default_min_role`, `sort_order`) VALUES
('menu.dashboard',    'Dashboard',            'menu', 'viewer',     10),
('menu.invoices',     'Invoices',             'menu', 'viewer',     20),
('menu.create',       'New Invoice',          'menu', 'sales',      30),
('menu.clients',      'Clients',              'menu', 'viewer',     40),
('menu.products',     'Services / Products',  'menu', 'sales',      50),
('menu.suppliers',    'Suppliers',            'menu', 'sales',      51),
('menu.purchases',    'Purchases',            'menu', 'sales',      52),
('menu.sales',        'Sales',                'menu', 'sales',      53),
('menu.stock',        'Stock Ledger',         'menu', 'sales',      54),
('menu.payments',     'Payments',             'menu', 'accountant', 60),
('menu.credit_notes', 'Credit Notes',         'menu', 'accountant', 70),
('menu.reports',      'Reports',              'menu', 'manager',    80),
('menu.aging',        'Aging Report',         'menu', 'manager',    90),
('menu.expenses',     'Expenses',             'menu', 'accountant', 100),
('menu.tax',          'Tax Summary',          'menu', 'accountant', 110),
('menu.reminders',    'Reminders',            'menu', 'manager',    120),
('menu.recurring',    'Recurring',            'menu', 'manager',    130),
('menu.portal',       'Client Portal',        'menu', 'viewer',     140),
('menu.activity',     'Activity Log',         'menu', 'manager',    150),
('menu.templates',    'PDF Templates',        'menu', 'admin',      160),
('menu.whatsapp',     'WhatsApp Setup',       'menu', 'admin',      170),
('menu.email_setup',  'Email Setup',          'menu', 'admin',      180),
('menu.settings',     'Settings',             'menu', 'owner',      190),
('menu.backup',       'Backup & Export',      'menu', 'owner',      200),
('menu.team',         'Team',                 'menu', 'owner',      210),
('menu.msglog',       'Message Log',          'menu', 'viewer',     220),
('action.delete',        'Delete Records',       'action', 'manager', 300),
('action.archive',       'Archive Records',      'action', 'manager', 310),
('action.edit',          'Edit Records',         'action', 'sales',   320),
('action.create',        'Create Records',       'action', 'sales',   330),
('action.approve_edits', 'Approve Edit Requests','action', 'admin',   340)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`), `category` = VALUES(`category`),
    `default_min_role` = VALUES(`default_min_role`), `sort_order` = VALUES(`sort_order`);

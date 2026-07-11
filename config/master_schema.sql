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

-- ── Permission system (see config/migrations/001_permissions_master.sql
--    for the full explanation and seed data) ──────────────────────
CREATE TABLE IF NOT EXISTS `permissions` (
    `key`              VARCHAR(64) NOT NULL PRIMARY KEY,
    `label`            VARCHAR(128) NOT NULL,
    `category`         VARCHAR(64) NOT NULL DEFAULT 'General',
    `default_min_role` VARCHAR(32) NOT NULL DEFAULT 'viewer',
    `sort_order`       INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plan_permissions` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `plan`           VARCHAR(32) NOT NULL,
    `permission_key` VARCHAR(64) NOT NULL,
    `enabled`        TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `plan_key` (`plan`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_permission_overrides` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`      INT NOT NULL,
    `permission_key` VARCHAR(64) NOT NULL,
    `enabled`        TINYINT(1) NOT NULL DEFAULT 1,
    `set_by`         INT NULL,
    UNIQUE KEY `tenant_key` (`tenant_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `permissions` (`key`, `label`, `category`, `default_min_role`, `sort_order`) VALUES
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
('menu.settings',     'Settings',            'Admin',          'admin',      250);

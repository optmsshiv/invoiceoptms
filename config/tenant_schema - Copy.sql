-- ================================================================
--  OPTMS Tenant Database Schema
--  This file is used by api/tenant.php to provision new tenant DBs
--  Do NOT add CREATE DATABASE or USE statements here
-- ================================================================

--

--
--

-- --------------------------------------------------------

--
-- Table structure for table `activitys_log`
--

CREATE TABLE `activitys_log` (
    `id` bigint(20) UNSIGNED NOT NULL,
    `type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
    `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `detail` text COLLATE utf8mb4_unicode_ci,
    `invoice_id` int(10) UNSIGNED DEFAULT NULL,
    `user_id` int(10) UNSIGNED DEFAULT NULL,
    `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
    `id` int(11) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
    `entity_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
    `entity_id` int(11) DEFAULT NULL,
    `details` text COLLATE utf8_unicode_ci,
    `ip_address` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
    `id` int(11) NOT NULL,
    `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
    `person` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
    `email` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
    `phone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
    `whatsapp` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
    `gst_number` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
    `address` text COLLATE utf8_unicode_ci,
    `landmark` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
    `color` varchar(10) COLLATE utf8_unicode_ci DEFAULT '#00897B',
    `logo` text COLLATE utf8_unicode_ci,
    `is_active` tinyint(1) DEFAULT '1',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `tags` text COLLATE utf8_unicode_ci,
    `extra_contacts` text COLLATE utf8_unicode_ci
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_notes`
--

CREATE TABLE `credit_notes` (
    `id` int(11) NOT NULL,
    `cn_number` varchar(50) NOT NULL,
    `invoice_id` int(11) DEFAULT NULL,
    `invoice_number` varchar(50) NOT NULL DEFAULT '',
    `client_name` varchar(200) NOT NULL DEFAULT '',
    `amount` decimal(12, 2) NOT NULL DEFAULT '0.00',
    `issued_date` date DEFAULT NULL,
    `reason` text NOT NULL,
    `notes` text,
    `status` enum(
        'Draft',
        'Issued',
        'Applied',
        'Void'
    ) NOT NULL DEFAULT 'Draft',
    `created_by` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_payments`
-- Audit backup: a copy of every payment row is written here (via the
-- trg_backup_payment_before_delete trigger on `payments`) right before
-- it's deleted, so a payment can always be traced/restored later.
--

CREATE TABLE `deleted_payments` (
    `id` int(11) NOT NULL,
    `original_id` int(11) NOT NULL,
    `invoice_id` int(11) DEFAULT NULL,
    `invoice_number` varchar(50) DEFAULT NULL,
    `client_name` varchar(200) DEFAULT NULL,
    `amount` decimal(14, 2) NOT NULL,
    `payment_date` date DEFAULT NULL,
    `method` varchar(100) DEFAULT NULL,
    `transaction_id` varchar(200) DEFAULT NULL,
    `status` varchar(20) DEFAULT NULL,
    `notes` text,
    `settlement_discount` decimal(10, 2) DEFAULT '0.00',
    `remaining_amt` decimal(10, 2) DEFAULT '0.00',
    `invoice_deleted` tinyint(1) NOT NULL DEFAULT '0',
    `created_at` timestamp NULL DEFAULT NULL,
    `deleted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_source` varchar(50) NOT NULL DEFAULT 'unknown'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
    `id` int(11) NOT NULL,
    `invoice_id` int(11) DEFAULT NULL,
    `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `client_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `to_email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
    `subject` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `body_html` mediumtext COLLATE utf8mb4_unicode_ci,
    `status` enum('sent', 'failed', 'pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
    `error_msg` text COLLATE utf8mb4_unicode_ci,
    `smtp_profile` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'default',
    `type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invoice' COMMENT 'invoice|estimate|receipt|reminder|overdue|followup|test',
    `track_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `opened_at` datetime DEFAULT NULL,
    `open_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
    `sent_at` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
    `id` int(11) NOT NULL,
    `type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'invoice|estimate|receipt|reminder|overdue|followup',
    `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
    `subject` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT '1',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
    `id` int(10) UNSIGNED NOT NULL,
    `date` date NOT NULL,
    `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Other',
    `vendor` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
    `amount` decimal(12, 2) NOT NULL DEFAULT '0.00',
    `method` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UPI',
    `notes` text COLLATE utf8mb4_unicode_ci,
    `created_by` int(10) UNSIGNED DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
    `id` int(11) NOT NULL,
    `invoice_number` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
    `client_id` int(11) DEFAULT NULL,
    `client_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
    `service_type` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
    `issued_date` date DEFAULT NULL,
    `due_date` date DEFAULT NULL,
    `status` enum(
        'Draft',
        'Pending',
        'Paid',
        'Overdue',
        'Partial',
        'Cancelled',
        'Estimate'
    ) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Draft',
    `cancel_reason` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Reason for cancellation, recorded at the time of status change',
    `currency` varchar(5) COLLATE utf8_unicode_ci DEFAULT '₹',
    `subtotal` decimal(14, 2) DEFAULT '0.00',
    `discount_pct` decimal(5, 2) DEFAULT '0.00',
    `discount_type` enum('percent', 'flat') COLLATE utf8_unicode_ci DEFAULT 'percent',
    `discount_amt` decimal(12, 2) DEFAULT '0.00',
    `gst_amount` decimal(12, 2) DEFAULT '0.00',
    `grand_total` decimal(14, 2) DEFAULT '0.00',
    `notes` text COLLATE utf8_unicode_ci,
    `bank_details` text COLLATE utf8_unicode_ci,
    `terms` text COLLATE utf8_unicode_ci,
    `company_logo` text COLLATE utf8_unicode_ci,
    `client_logo` text COLLATE utf8_unicode_ci,
    `signature` text COLLATE utf8_unicode_ci,
    `qr_code` text COLLATE utf8_unicode_ci,
    `template_id` tinyint(4) DEFAULT '1',
    `generated_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT 'OPTMS Tech Invoice Manager',
    `show_generated` tinyint(1) DEFAULT '1',
    `pdf_options` json DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_estimate` tinyint(1) DEFAULT '0',
    `client_person` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
    `client_wa` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
    `client_email` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
    `client_gst` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
    `client_addr` text COLLATE utf8_unicode_ci
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
    `id` int(11) NOT NULL,
    `invoice_id` int(11) NOT NULL,
    `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
    `quantity` decimal(10, 2) DEFAULT '1.00',
    `rate` decimal(12, 2) DEFAULT '0.00',
    `gst_rate` decimal(5, 2) DEFAULT '18.00',
    `line_total` decimal(14, 2) DEFAULT '0.00',
    `sort_order` int(11) DEFAULT '0'
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_portal_tokens`
--

CREATE TABLE `invoice_portal_tokens` (
    `id` int(11) NOT NULL,
    `invoice_id` int(11) NOT NULL,
    `token` varchar(64) NOT NULL,
    `created_at` datetime DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
    `id` int(11) NOT NULL,
    `invoice_id` int(11) DEFAULT NULL,
    `invoice_number` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
    `client_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
    `amount` decimal(14, 2) NOT NULL,
    `payment_date` datetime DEFAULT NULL,
    `method` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
    `transaction_id` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
    `status` enum(
        'Success',
        'Pending',
        'Failed'
    ) COLLATE utf8_unicode_ci DEFAULT 'Success',
    `notes` text COLLATE utf8_unicode_ci,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `settlement_discount` decimal(10, 2) DEFAULT '0.00',
    `remaining_amt` decimal(10, 2) NOT NULL DEFAULT '0.00',
    `invoice_deleted` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

--
-- Triggers `payments`
--
DELIMITER $$

CREATE TRIGGER `trg_backup_payment_before_delete` BEFORE DELETE ON `payments` FOR EACH ROW BEGIN
  INSERT INTO `deleted_payments` (
    original_id, invoice_id, invoice_number, client_name,
    amount, payment_date, method, transaction_id, status,
    notes, settlement_discount, remaining_amt,
    invoice_deleted, created_at, deleted_source
  ) VALUES (
    OLD.id, OLD.invoice_id, OLD.invoice_number, OLD.client_name,
    OLD.amount, OLD.payment_date, OLD.method, OLD.transaction_id, OLD.status,
    OLD.notes, OLD.settlement_discount, OLD.remaining_amt,
    OLD.invoice_deleted, OLD.created_at, 'trigger_auto'
  );
END
$$

DELIMITER;

-- --------------------------------------------------------

--
-- Table structure for table `portal_tokens`
--

CREATE TABLE `portal_tokens` (
    `id` int(10) UNSIGNED NOT NULL,
    `invoice_id` int(10) UNSIGNED NOT NULL,
    `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `views` int(10) UNSIGNED NOT NULL DEFAULT '0',
    `last_viewed` datetime DEFAULT NULL,
    `expires_at` datetime DEFAULT NULL COMMENT 'NULL = never expires',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `first_viewed` datetime DEFAULT NULL,
    `view_count` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portal_views`
--

CREATE TABLE `portal_views` (
    `invoice_id` int(10) UNSIGNED NOT NULL,
    `first_viewed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `view_count` int(10) UNSIGNED NOT NULL DEFAULT '1'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
    `id` int(11) NOT NULL,
    `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
    `category` varchar(100) COLLATE utf8_unicode_ci DEFAULT 'Other',
    `rate` decimal(12, 2) NOT NULL DEFAULT '0.00',
    `hsn_code` varchar(20) COLLATE utf8_unicode_ci DEFAULT '998314',
    `gst_rate` decimal(5, 2) DEFAULT '18.00',
    `description` text COLLATE utf8_unicode_ci,
    `is_active` tinyint(1) DEFAULT '1',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
    `id` int(11) NOT NULL,
    `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `color` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#00897B',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promise_to_pay`
--

CREATE TABLE `promise_to_pay` (
    `id` int(10) UNSIGNED NOT NULL,
    `invoice_id` int(10) UNSIGNED NOT NULL,
    `invoice_num` varchar(40) NOT NULL DEFAULT '',
    `client_name` varchar(200) NOT NULL DEFAULT '',
    `promise_date` date NOT NULL,
    `amount` decimal(12, 2) NOT NULL DEFAULT '0.00',
    `note` text,
    `channel` varchar(20) NOT NULL DEFAULT 'whatsapp',
    `status` varchar(20) NOT NULL DEFAULT 'pending',
    `reminded_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_schedules`
--

CREATE TABLE `recurring_schedules` (
    `id` int(11) NOT NULL,
    `client_id` int(11) NOT NULL,
    `client_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
    `service` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
    `amount` decimal(10, 2) DEFAULT NULL,
    `discount_pct` decimal(5, 2) DEFAULT '0.00',
    `disc_type` varchar(10) COLLATE utf8_unicode_ci DEFAULT 'pct',
    `disc_val` decimal(10, 2) DEFAULT '0.00',
    `discount_amt` decimal(10, 2) DEFAULT '0.00',
    `gst` decimal(5, 2) DEFAULT '0.00',
    `gst_amt` decimal(10, 2) DEFAULT '0.00',
    `grand_total` decimal(10, 2) DEFAULT NULL,
    `items` json DEFAULT NULL,
    `freq` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
    `next_date` date DEFAULT NULL,
    `end_date` date DEFAULT NULL,
    `due_days` int(11) DEFAULT '15',
    `template_id` int(11) DEFAULT '1',
    `notes` text COLLATE utf8_unicode_ci,
    `status` varchar(20) COLLATE utf8_unicode_ci DEFAULT 'active',
    `generated_count` int(11) DEFAULT '0',
    `last_generated` date DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_schedule_items`
--

CREATE TABLE `recurring_schedule_items` (
    `id` int(11) NOT NULL,
    `schedule_id` int(11) NOT NULL,
    `description` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
    `item_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
    `qty` decimal(10, 2) DEFAULT NULL,
    `rate` decimal(10, 2) DEFAULT NULL,
    `gst_pct` decimal(5, 2) DEFAULT '0.00'
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminder_log`
--

CREATE TABLE `reminder_log` (
    `id` int(10) UNSIGNED NOT NULL,
    `invoice_id` int(10) UNSIGNED DEFAULT NULL,
    `invoice_num` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `client_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'due_reminder' COMMENT 'due_soon | due_today | overdue | manual',
    `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
    `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent' COMMENT 'sent | skipped | failed',
    `message` text COLLATE utf8mb4_unicode_ci,
    `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminder_settings`
--

CREATE TABLE `reminder_settings` (
    `id` int(10) UNSIGNED NOT NULL,
    `before_days` tinyint(4) NOT NULL DEFAULT '3' COMMENT 'Days before due to send reminder',
    `on_due` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = send reminder on due date',
    `overdue_freq` tinyint(4) NOT NULL DEFAULT '7' COMMENT 'Re-send overdue reminder every N days',
    `max_overdue` tinyint(4) NOT NULL DEFAULT '3' COMMENT 'Max overdue reminder attempts',
    `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp' COMMENT 'whatsapp | email | both',
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `send_hour` tinyint(4) NOT NULL DEFAULT '9',
    `send_minute` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
    `id` int(11) NOT NULL,
    `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
    `color` varchar(10) COLLATE utf8_unicode_ci DEFAULT '#00897B',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
    `id` int(11) NOT NULL,
    `key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
    `value` text COLLATE utf8_unicode_ci,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `smtp_profiles`
--

CREATE TABLE `smtp_profiles` (
    `id` int(11) NOT NULL,
    `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `host` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
    `port` smallint(6) NOT NULL DEFAULT '587',
    `username` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `from_email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
    `from_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPTMS Tech',
    `encryption` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tls' COMMENT 'tls | ssl | none',
    `provider` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'smtp' COMMENT 'smtp | gmail | sendgrid | mailgun',
    `api_key` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `is_default` tinyint(1) NOT NULL DEFAULT '0',
    `is_active` tinyint(1) NOT NULL DEFAULT '1',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
    `id` int(11) NOT NULL,
    `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
    `email` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
    `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `role` enum('admin', 'staff') COLLATE utf8_unicode_ci DEFAULT 'admin',
    `avatar` text COLLATE utf8_unicode_ci,
    `is_active` tinyint(1) DEFAULT '1',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_message_log`
--

CREATE TABLE `wa_message_log` (
    `id` int(10) UNSIGNED NOT NULL,
    `entry_id` varchar(40) NOT NULL,
    `wamid` varchar(100) DEFAULT NULL,
    `ts` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type` varchar(40) NOT NULL DEFAULT 'unknown',
    `status` varchar(20) NOT NULL DEFAULT 'sent_web',
    `client` varchar(200) DEFAULT NULL,
    `phone` varchar(30) DEFAULT NULL,
    `inv_id` varchar(20) DEFAULT NULL,
    `inv_num` varchar(40) DEFAULT NULL,
    `inv_amt` varchar(30) DEFAULT NULL,
    `inv_status` varchar(30) DEFAULT NULL,
    `msg` text,
    `error` varchar(500) DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activitys_log`
--
ALTER TABLE `activitys_log`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_actlog_type` (`type`),
ADD KEY `idx_actlog_invoice` (`invoice_id`),
ADD KEY `idx_actlog_created` (`created_at`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
ADD PRIMARY KEY (`id`),
ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients` ADD PRIMARY KEY (`id`);

--
-- Indexes for table `credit_notes`
--
ALTER TABLE `credit_notes`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `uk_cn_number` (`cn_number`);

--
-- Indexes for table `deleted_payments`
--
ALTER TABLE `deleted_payments`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_del_inv_num` (`invoice_number`),
ADD KEY `idx_del_orig_id` (`original_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `track_token` (`track_token`),
ADD KEY `idx_el_invoice` (`invoice_id`),
ADD KEY `idx_el_status` (`status`),
ADD KEY `idx_el_type` (`type`),
ADD KEY `idx_el_created` (`created_at`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `type` (`type`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_expenses_date` (`date`),
ADD KEY `idx_expenses_category` (`category`),
ADD KEY `idx_expenses_created` (`created_at`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `invoice_number` (`invoice_number`),
ADD KEY `client_id` (`client_id`),
ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
ADD PRIMARY KEY (`id`),
ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `invoice_portal_tokens`
--
ALTER TABLE `invoice_portal_tokens`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `token` (`token`),
ADD KEY `idx_invoice` (`invoice_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
ADD PRIMARY KEY (`id`),
ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `portal_tokens`
--
ALTER TABLE `portal_tokens`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `uk_portal_invoice` (`invoice_id`),
ADD UNIQUE KEY `uk_portal_token` (`token`),
ADD UNIQUE KEY `invoice_id` (`invoice_id`),
ADD KEY `idx_portal_token` (`token`);

--
-- Indexes for table `portal_views`
--
ALTER TABLE `portal_views` ADD PRIMARY KEY (`invoice_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products` ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `promise_to_pay`
--
ALTER TABLE `promise_to_pay`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_ptp_inv` (`invoice_id`),
ADD KEY `idx_ptp_date` (`promise_date`);

--
-- Indexes for table `recurring_schedules`
--
ALTER TABLE `recurring_schedules` ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recurring_schedule_items`
--
ALTER TABLE `recurring_schedule_items`
ADD PRIMARY KEY (`id`),
ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `reminder_log`
--
ALTER TABLE `reminder_log`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_remlog_invoice` (`invoice_id`),
ADD KEY `idx_remlog_sent` (`sent_at`);

--
-- Indexes for table `reminder_settings`
--
ALTER TABLE `reminder_settings` ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories` ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `smtp_profiles`
--
ALTER TABLE `smtp_profiles` ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wa_message_log`
--
ALTER TABLE `wa_message_log`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `uk_entry_id` (`entry_id`),
ADD KEY `idx_wa_log_ts` (`ts`),
ADD KEY `idx_wa_log_inv` (`inv_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activitys_log`
--
ALTER TABLE `activitys_log`
MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_notes`
--
ALTER TABLE `credit_notes`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deleted_payments`
--
ALTER TABLE `deleted_payments`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_portal_tokens`
--
ALTER TABLE `invoice_portal_tokens`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portal_tokens`
--
ALTER TABLE `portal_tokens`
MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promise_to_pay`
--
ALTER TABLE `promise_to_pay`
MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recurring_schedules`
--
ALTER TABLE `recurring_schedules`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recurring_schedule_items`
--
ALTER TABLE `recurring_schedule_items`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reminder_log`
--
ALTER TABLE `reminder_log`
MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reminder_settings`
--
ALTER TABLE `reminder_settings`
MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `smtp_profiles`
--
ALTER TABLE `smtp_profiles`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_message_log`
--
ALTER TABLE `wa_message_log`
MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `recurring_schedule_items`
--
ALTER TABLE `recurring_schedule_items`
ADD CONSTRAINT `recurring_schedule_items_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `recurring_schedules` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
-- Per-role menu/action permissions, managed by the tenant owner.
-- owner/super_admin are never restricted by this table — they always
-- get whatever the tenant's plan/override ceiling allows (see the
-- master DB's plan_permissions / tenant_permission_overrides).
--

CREATE TABLE `role_permissions` (
    `id` int(10) UNSIGNED NOT NULL,
    `role` enum(
        'admin',
        'manager',
        'accountant',
        'sales',
        'viewer'
    ) NOT NULL,
    `permission_key` varchar(80) NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT 0,
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `uk_role_perm` (`role`, `permission_key`);

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
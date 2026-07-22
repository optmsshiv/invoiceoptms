-- ================================================================
--  OPTMS Tenant Database Schema (structure only)
--  Rebuilt from a working tenant export (edrppymy_sneha_enterprises)
--  after config/tenant_schema.sql on the server was found to contain
--  tenant.php's PHP source instead of SQL — see chat explanation.
-- ================================================================

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 21, 2026 at 03:11 PM
-- Server version: 5.7.44-48
-- PHP Version: 8.3.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Tenant database schema template (applied to a freshly created,
-- empty per-tenant database by _runTenantSchema() in api/tenant.php)
--

-- --------------------------------------------------------

--
-- Table structure for table `activitys_log`
--

CREATE TABLE IF NOT EXISTS `activitys_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text COLLATE utf8_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE IF NOT EXISTS `clients` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_notes`
--

CREATE TABLE IF NOT EXISTS `credit_notes` (
  `id` int(11) NOT NULL,
  `cn_number` varchar(50) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL DEFAULT '',
  `client_name` varchar(200) NOT NULL DEFAULT '',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `issued_date` date DEFAULT NULL,
  `reason` text NOT NULL,
  `notes` text,
  `status` enum('Draft','Issued','Applied','Void') NOT NULL DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `customer_type` varchar(30) DEFAULT 'Domestic',
  `mobile` varchar(30) DEFAULT '',
  `email` varchar(150) DEFAULT '',
  `gstin` varchar(20) DEFAULT '',
  `state` varchar(50) DEFAULT '',
  `district` varchar(50) DEFAULT '',
  `billing_address` text,
  `shipping_address` text,
  `credit_limit` decimal(14,2) DEFAULT '0.00',
  `payment_terms` varchar(50) DEFAULT '',
  `sales_executive` varchar(100) DEFAULT '',
  `notes` text,
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `customer_code` varchar(30) DEFAULT '',
  `business_name` varchar(150) DEFAULT '',
  `display_name` varchar(150) DEFAULT '',
  `group_name` varchar(50) DEFAULT '',
  `alternate_phone` varchar(20) DEFAULT '',
  `whatsapp_no` varchar(20) DEFAULT '',
  `billing_city` varchar(80) DEFAULT '',
  `billing_pincode` varchar(12) DEFAULT '',
  `shipping_city` varchar(80) DEFAULT '',
  `shipping_state` varchar(50) DEFAULT '',
  `shipping_pincode` varchar(12) DEFAULT '',
  `pan_no` varchar(20) DEFAULT '',
  `business_type` varchar(50) DEFAULT '',
  `tan_no` varchar(20) DEFAULT '',
  `iec_no` varchar(20) DEFAULT '',
  `trade_license_no` varchar(30) DEFAULT '',
  `currency` varchar(10) DEFAULT 'INR',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `opening_balance_type` varchar(10) DEFAULT 'Debit',
  `documents` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `edit_approval_requests`
--

CREATE TABLE IF NOT EXISTS `edit_approval_requests` (
  `id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requester_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `entity_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_label` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('pending','approved','rejected','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewer_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `status` enum('sent','failed','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_msg` text COLLATE utf8mb4_unicode_ci,
  `smtp_profile` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'default',
  `type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invoice' COMMENT 'invoice|estimate|receipt|reminder|overdue|followup|test',
  `track_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `open_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL,
  `type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'invoice|estimate|receipt|reminder|overdue|followup',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Other',
  `vendor` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `method` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UPI',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `service_type` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Draft','Pending','Paid','Overdue','Partial','Cancelled','Estimate') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Draft',
  `cancel_reason` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Reason for cancellation, recorded at the time of status change',
  `currency` varchar(5) COLLATE utf8_unicode_ci DEFAULT '₹',
  `subtotal` decimal(14,2) DEFAULT '0.00',
  `discount_pct` decimal(5,2) DEFAULT '0.00',
  `discount_type` enum('percent','flat') COLLATE utf8_unicode_ci DEFAULT 'percent',
  `discount_amt` decimal(12,2) DEFAULT '0.00',
  `gst_amount` decimal(12,2) DEFAULT '0.00',
  `grand_total` decimal(14,2) DEFAULT '0.00',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `item_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Service',
  `quantity` decimal(10,2) DEFAULT '1.00',
  `rate` decimal(12,2) DEFAULT '0.00',
  `gst_rate` decimal(5,2) DEFAULT '18.00',
  `line_total` decimal(14,2) DEFAULT '0.00',
  `sort_order` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_portal_tokens`
--

CREATE TABLE IF NOT EXISTS `invoice_portal_tokens` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `client_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  `method` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` enum('Success','Pending','Failed') COLLATE utf8_unicode_ci DEFAULT 'Success',
  `notes` text COLLATE utf8_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `settlement_discount` decimal(10,2) DEFAULT '0.00',
  `remaining_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `invoice_deleted` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_vouchers`
--

CREATE TABLE IF NOT EXISTS `payment_vouchers` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `direction` enum('in','out') DEFAULT 'out',
  `party_type` varchar(30) DEFAULT 'Vendor',
  `party_name` varchar(150) NOT NULL,
  `payment_for` varchar(255) DEFAULT '',
  `payment_mode` varchar(30) DEFAULT 'Cash',
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('Paid','Pending') DEFAULT 'Paid',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `portal_tokens`
--

CREATE TABLE IF NOT EXISTS `portal_tokens` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portal_views`
--

CREATE TABLE IF NOT EXISTS `portal_views` (
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `first_viewed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `view_count` int(10) UNSIGNED NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8_unicode_ci DEFAULT 'Other',
  `unit_family` enum('count','weight','volume') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'count',
  `rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `hsn_code` varchar(20) COLLATE utf8_unicode_ci DEFAULT '998314',
  `gst_rate` decimal(5,2) DEFAULT '18.00',
  `description` text COLLATE utf8_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `hsn` varchar(20) COLLATE utf8_unicode_ci DEFAULT '',
  `status` enum('active','archived') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `sku` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `unit` varchar(20) COLLATE utf8_unicode_ci DEFAULT 'Kg',
  `brand` varchar(100) COLLATE utf8_unicode_ci DEFAULT '',
  `variety` varchar(100) COLLATE utf8_unicode_ci DEFAULT '',
  `grade` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `barcode` varchar(100) COLLATE utf8_unicode_ci DEFAULT '',
  `shelf_life_months` int(11) DEFAULT NULL,
  `storage_type` varchar(30) COLLATE utf8_unicode_ci DEFAULT '',
  `base_unit_label` varchar(20) COLLATE utf8_unicode_ci DEFAULT 'Kg',
  `sale_unit` varchar(20) COLLATE utf8_unicode_ci DEFAULT 'Kg',
  `purchase_unit` varchar(20) COLLATE utf8_unicode_ci DEFAULT 'Kg',
  `min_order_qty` decimal(12,2) DEFAULT '0.00',
  `moisture_limit` decimal(5,2) DEFAULT NULL,
  `foreign_matter_limit` decimal(5,2) DEFAULT NULL,
  `broken_damage_limit` decimal(5,2) DEFAULT NULL,
  `oil_content` decimal(5,2) DEFAULT NULL,
  `admixture_limit` decimal(5,2) DEFAULT NULL,
  `color` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `aroma` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `shape_size` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `packing_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `packing_size` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `purchase_rate` decimal(12,2) DEFAULT '0.00',
  `sale_rate` decimal(12,2) DEFAULT '0.00',
  `mrp` decimal(12,2) DEFAULT '0.00',
  `gst` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_type` varchar(40) COLLATE utf8_unicode_ci DEFAULT 'Intra-State (CGST+SGST)',
  `opening_stock` decimal(12,3) DEFAULT '0.000',
  `reorder_level` decimal(12,3) DEFAULT '0.000',
  `max_stock` decimal(12,3) DEFAULT '0.000',
  `default_warehouse` varchar(100) COLLATE utf8_unicode_ci DEFAULT 'Main Warehouse',
  `track_batch` tinyint(1) DEFAULT '0',
  `track_serial` tinyint(1) DEFAULT '0',
  `short_description` varchar(200) COLLATE utf8_unicode_ci DEFAULT '',
  `detailed_description` varchar(500) COLLATE utf8_unicode_ci DEFAULT '',
  `country_of_origin` varchar(80) COLLATE utf8_unicode_ci DEFAULT 'India',
  `manufacturer` varchar(150) COLLATE utf8_unicode_ci DEFAULT '',
  `fssai_license` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `iec_code` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `tags` text COLLATE utf8_unicode_ci,
  `images` text COLLATE utf8_unicode_ci,
  `attachments` text COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#00897B',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proforma_invoices`
--

CREATE TABLE IF NOT EXISTS `proforma_invoices` (
  `id` int(11) NOT NULL,
  `ofr_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `ofr_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `destination` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `incoterms` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'FOB',
  `payment_terms` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `currency` enum('INR','USD','BOTH') COLLATE utf8mb4_unicode_ci DEFAULT 'BOTH',
  `usd_rate` decimal(10,4) DEFAULT '93.5000',
  `is_international` tinyint(1) DEFAULT '1',
  `products` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of product rows',
  `charges` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of charge rows',
  `subtotal_inr` decimal(14,2) DEFAULT '0.00',
  `total_inr` decimal(14,2) DEFAULT '0.00',
  `total_usd` decimal(14,2) DEFAULT '0.00',
  `per_kg_inr` decimal(10,2) DEFAULT '0.00',
  `per_kg_usd` decimal(10,4) DEFAULT '0.0000',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Draft','Pending','Accepted','Cancelled','Expired') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `sale_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promise_to_pay`
--

CREATE TABLE IF NOT EXISTS `promise_to_pay` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `invoice_num` varchar(40) NOT NULL DEFAULT '',
  `client_name` varchar(200) NOT NULL DEFAULT '',
  `promise_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `note` text,
  `channel` varchar(20) NOT NULL DEFAULT 'whatsapp',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reminded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE IF NOT EXISTS `purchases` (
  `id` int(11) NOT NULL,
  `purchase_no` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_invoice_ref` varchar(100) DEFAULT '',
  `purchase_date` date NOT NULL,
  `currency` varchar(10) DEFAULT 'INR',
  `exchange_rate` decimal(12,4) DEFAULT '1.0000',
  `subtotal` decimal(14,2) DEFAULT '0.00',
  `gst_amount` decimal(14,2) DEFAULT '0.00',
  `total` decimal(14,2) DEFAULT '0.00',
  `amount_paid` decimal(14,2) DEFAULT '0.00',
  `status` enum('Pending','Received','Partial','Paid') DEFAULT 'Pending',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `gst_pct` decimal(5,2) DEFAULT '0.00',
  `reference_po_no` varchar(50) DEFAULT '',
  `supplier_type` varchar(30) DEFAULT '',
  `gst_applicable` tinyint(1) NOT NULL DEFAULT '1',
  `supply_type` varchar(20) DEFAULT 'Intra-State',
  `transport_mode` varchar(30) DEFAULT '',
  `vehicle_no` varchar(30) DEFAULT '',
  `driver_name` varchar(100) DEFAULT '',
  `warehouse` varchar(100) DEFAULT 'Main Warehouse',
  `payment_terms` varchar(50) DEFAULT '',
  `payment_type` varchar(30) DEFAULT '',
  `remarks` varchar(255) DEFAULT '',
  `transport_charge` decimal(12,2) DEFAULT '0.00',
  `loading_charge` decimal(12,2) DEFAULT '0.00',
  `packing_charge` decimal(12,2) DEFAULT '0.00',
  `other_charges` decimal(12,2) DEFAULT '0.00',
  `discount_amount` decimal(12,2) DEFAULT '0.00',
  `discount_remarks` varchar(255) NOT NULL DEFAULT '',
  `attachment_path` varchar(255) DEFAULT '',
  `payment_mode` varchar(255) DEFAULT '',
  `transaction_no` varchar(100) DEFAULT '',
  `payment_date` date DEFAULT NULL,
  `weighing_type` varchar(30) DEFAULT 'Dharam Kanta',
  `kanta_name` varchar(150) DEFAULT '',
  `weighbridge_slip_no` varchar(50) DEFAULT '',
  `weight_datetime` datetime DEFAULT NULL,
  `kanta_gross_weight` decimal(12,2) DEFAULT '0.00',
  `kanta_tare_weight` decimal(12,2) DEFAULT '0.00',
  `kanta_operator_name` varchar(100) DEFAULT '',
  `kanta_slip_path` varchar(255) DEFAULT '',
  `header_moisture_pct` decimal(5,2) DEFAULT NULL,
  `header_impurity_pct` decimal(5,2) DEFAULT NULL,
  `header_dhalta_pct` decimal(5,2) DEFAULT NULL,
  `header_dhalta_kg` decimal(12,2) DEFAULT NULL,
  `header_billable_weight` decimal(12,2) DEFAULT NULL,
  `deductions` text,
  `deduction_amount` decimal(12,2) DEFAULT '0.00',
  `trade_discount_pct` decimal(5,2) DEFAULT '0.00',
  `cash_discount_pct` decimal(5,2) DEFAULT '0.00',
  `cd_applicable_within` varchar(20) DEFAULT 'Same Day',
  `trade_discount_amount` decimal(12,2) DEFAULT '0.00',
  `cash_discount_amount` decimal(12,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `hsn` varchar(20) DEFAULT '',
  `qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `entered_qty` decimal(12,3) DEFAULT NULL,
  `entered_unit` varchar(10) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `rate` decimal(14,2) NOT NULL DEFAULT '0.00',
  `gst_pct` decimal(5,2) DEFAULT '0.00',
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `variety_grade` varchar(100) DEFAULT '',
  `moisture_pct` decimal(5,2) DEFAULT '0.00',
  `quality_grade` varchar(50) DEFAULT '',
  `gross_weight` decimal(12,3) DEFAULT '0.000',
  `tare_weight` decimal(12,3) DEFAULT '0.000',
  `dhalta_pct` decimal(5,2) DEFAULT '0.00',
  `dhalta_kg` decimal(12,3) DEFAULT '0.000',
  `billable_weight` decimal(12,3) DEFAULT '0.000',
  `discount_pct` decimal(5,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_schedules`
--

CREATE TABLE IF NOT EXISTS `recurring_schedules` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `client_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `service` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `discount_pct` decimal(5,2) DEFAULT '0.00',
  `disc_type` varchar(10) COLLATE utf8_unicode_ci DEFAULT 'pct',
  `disc_val` decimal(10,2) DEFAULT '0.00',
  `discount_amt` decimal(10,2) DEFAULT '0.00',
  `gst` decimal(5,2) DEFAULT '0.00',
  `gst_amt` decimal(10,2) DEFAULT '0.00',
  `grand_total` decimal(10,2) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_schedule_items`
--

CREATE TABLE IF NOT EXISTS `recurring_schedule_items` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `item_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `gst_pct` decimal(5,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminder_log`
--

CREATE TABLE IF NOT EXISTS `reminder_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED DEFAULT NULL,
  `invoice_num` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'due_reminder' COMMENT 'due_soon | due_today | overdue | manual',
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent' COMMENT 'sent | skipped | failed',
  `message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminder_settings`
--

CREATE TABLE IF NOT EXISTS `reminder_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `before_days` tinyint(4) NOT NULL DEFAULT '3' COMMENT 'Days before due to send reminder',
  `on_due` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = send reminder on due date',
  `overdue_freq` tinyint(4) NOT NULL DEFAULT '7' COMMENT 'Re-send overdue reminder every N days',
  `max_overdue` tinyint(4) NOT NULL DEFAULT '3' COMMENT 'Max overdue reminder attempts',
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp' COMMENT 'whatsapp | email | both',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `send_hour` tinyint(4) NOT NULL DEFAULT '9',
  `send_minute` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` enum('admin','manager','accountant','sales','viewer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE IF NOT EXISTS `sales` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `sales_executive` varchar(100) DEFAULT '',
  `payment_terms` varchar(50) DEFAULT '',
  `sales_type` varchar(30) DEFAULT 'Local Sales',
  `place_of_supply` varchar(50) DEFAULT '',
  `currency` varchar(10) DEFAULT 'INR',
  `subtotal` decimal(14,2) DEFAULT '0.00',
  `transport_charge` decimal(12,2) DEFAULT '0.00',
  `loading_charge` decimal(12,2) DEFAULT '0.00',
  `packing_charge` decimal(12,2) DEFAULT '0.00',
  `insurance_charge` decimal(12,2) DEFAULT '0.00',
  `other_charges` decimal(12,2) DEFAULT '0.00',
  `round_off` decimal(8,2) DEFAULT '0.00',
  `discount_amount` decimal(12,2) DEFAULT '0.00',
  `discount_remarks` varchar(255) NOT NULL DEFAULT '',
  `taxable_amount` decimal(14,2) DEFAULT '0.00',
  `cgst_amount` decimal(12,2) DEFAULT '0.00',
  `sgst_amount` decimal(12,2) DEFAULT '0.00',
  `igst_amount` decimal(12,2) DEFAULT '0.00',
  `total_tax` decimal(12,2) DEFAULT '0.00',
  `total` decimal(14,2) DEFAULT '0.00',
  `payment_status` enum('Pending','Partial','Paid') DEFAULT 'Pending',
  `payment_method` varchar(30) DEFAULT '',
  `amount_received` decimal(14,2) DEFAULT '0.00',
  `transaction_no` varchar(100) DEFAULT '',
  `payment_date` date DEFAULT NULL,
  `customer_notes` text,
  `internal_notes` text,
  `delivery_instructions` text,
  `attachments` text,
  `prepared_by` varchar(100) DEFAULT '',
  `checked_by` varchar(100) DEFAULT '',
  `approved_by` varchar(100) DEFAULT '',
  `status` enum('Draft','Confirmed','Cancelled') DEFAULT 'Confirmed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `weighing_type` varchar(30) DEFAULT 'Dharam Kanta',
  `kanta_name` varchar(150) DEFAULT '',
  `weighbridge_slip_no` varchar(50) DEFAULT '',
  `weight_datetime` datetime DEFAULT NULL,
  `kanta_operator_name` varchar(100) DEFAULT '',
  `kanta_gross_weight` decimal(12,2) DEFAULT '0.00',
  `kanta_tare_weight` decimal(12,2) DEFAULT '0.00',
  `kanta_moisture_pct` decimal(5,2) DEFAULT NULL,
  `kanta_dhalta_kg` decimal(12,2) DEFAULT '0.00',
  `deductions` text,
  `deduction_amount` decimal(12,2) DEFAULT '0.00',
  `trade_discount_pct` decimal(5,2) DEFAULT '0.00',
  `cash_discount_pct` decimal(5,2) DEFAULT '0.00',
  `cd_applicable_within` varchar(20) DEFAULT 'Same Day',
  `trade_discount_amount` decimal(12,2) DEFAULT '0.00',
  `cash_discount_amount` decimal(12,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `variety_grade` varchar(100) DEFAULT '',
  `batch_no` varchar(50) DEFAULT '',
  `warehouse` varchar(100) DEFAULT 'Main Warehouse',
  `qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `unit` varchar(20) DEFAULT 'Kg',
  `rate` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_pct` decimal(5,2) DEFAULT '0.00',
  `gst_pct` decimal(5,2) DEFAULT '0.00',
  `tax_amount` decimal(12,2) DEFAULT '0.00',
  `line_total` decimal(14,2) DEFAULT '0.00',
  `moisture_pct` decimal(5,2) DEFAULT NULL,
  `gross_wt` decimal(12,2) DEFAULT '0.00',
  `tare_wt` decimal(12,2) DEFAULT '0.00',
  `net_wt` decimal(12,2) DEFAULT '0.00',
  `dhalta_kg` decimal(12,2) DEFAULT '0.00',
  `billable_wt` decimal(12,2) DEFAULT '0.00',
  `kanta_slip` varchar(50) DEFAULT '',
  `kanta_data` text COMMENT 'JSON: {gross, tare, net, dhalta, billable, slip} — per-item kanta weights'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE IF NOT EXISTS `service_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `color` varchar(10) COLLATE utf8_unicode_ci DEFAULT '#00897B',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `smtp_profiles`
--

CREATE TABLE IF NOT EXISTS `smtp_profiles` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` int(11) NOT NULL,
  `adjustment_no` varchar(50) NOT NULL,
  `adjustment_date` date NOT NULL,
  `adjustment_type` varchar(30) DEFAULT 'Moisture Loss',
  `direction` varchar(3) NOT NULL DEFAULT 'out',
  `warehouse` varchar(100) DEFAULT 'Main Warehouse',
  `reference_no` varchar(100) DEFAULT '',
  `reference_date` date DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `variety_grade` varchar(100) DEFAULT '',
  `grade` varchar(50) DEFAULT '',
  `unit` varchar(20) DEFAULT 'Kg',
  `batch_no` varchar(50) DEFAULT '',
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `opening_stock` decimal(12,3) NOT NULL DEFAULT '0.000',
  `moisture_before_pct` decimal(5,2) DEFAULT NULL,
  `moisture_after_pct` decimal(5,2) DEFAULT NULL,
  `moisture_loss_pct` decimal(5,2) DEFAULT NULL,
  `weight_loss_kg` decimal(12,3) NOT NULL DEFAULT '0.000',
  `final_stock` decimal(12,3) NOT NULL DEFAULT '0.000',
  `reason` varchar(100) DEFAULT '',
  `remarks` text,
  `attachment_path` varchar(255) DEFAULT '',
  `approved_by` varchar(100) DEFAULT '',
  `approval_date` date DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `stock_in_entries`
--

CREATE TABLE IF NOT EXISTS `stock_in_entries` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `reference_date` date NOT NULL,
  `warehouse` varchar(100) DEFAULT 'Main Warehouse',
  `stock_in_type` varchar(30) DEFAULT 'Purchase',
  `remarks` text,
  `weighing_type` varchar(30) DEFAULT 'Own Weighbridge',
  `weighbridge_name` varchar(150) DEFAULT '',
  `weighbridge_slip_no` varchar(50) DEFAULT '',
  `weight_datetime` datetime DEFAULT NULL,
  `gross_weight` decimal(12,2) DEFAULT '0.00',
  `tare_weight` decimal(12,2) DEFAULT '0.00',
  `operator_name` varchar(100) DEFAULT '',
  `slip_path` varchar(255) DEFAULT '',
  `supplier_id` int(11) DEFAULT NULL,
  `challan_no` varchar(50) DEFAULT '',
  `challan_date` date DEFAULT NULL,
  `vehicle_no` varchar(30) DEFAULT '',
  `driver_name` varchar(100) DEFAULT '',
  `attachments` text,
  `total_quantity` decimal(12,3) DEFAULT '0.000',
  `total_amount` decimal(14,2) DEFAULT '0.00',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `stock_in_items`
--

CREATE TABLE IF NOT EXISTS `stock_in_items` (
  `id` int(11) NOT NULL,
  `stock_in_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variety` varchar(100) DEFAULT '',
  `batch_no` varchar(50) DEFAULT '',
  `mfg_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `rate` decimal(14,2) DEFAULT '0.00',
  `amount` decimal(14,2) DEFAULT '0.00',
  `grade` varchar(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `stock_ledger`
--

CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `ref_type` enum('purchase','sale','adjustment') NOT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `direction` enum('in','out') NOT NULL,
  `qty` decimal(12,3) NOT NULL,
  `rate` decimal(14,2) DEFAULT '0.00',
  `balance_after` decimal(12,3) NOT NULL DEFAULT '0.000',
  `movement_date` date NOT NULL,
  `notes` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `warehouse` varchar(100) DEFAULT 'Main Warehouse',
  `batch_no` varchar(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT '',
  `phone` varchar(30) DEFAULT '',
  `email` varchar(150) DEFAULT '',
  `gst_number` varchar(20) DEFAULT '',
  `country` varchar(80) DEFAULT 'India',
  `address` text,
  `payment_terms` varchar(100) DEFAULT '',
  `opening_balance` decimal(12,2) DEFAULT '0.00',
  `notes` text,
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `supplier_type` varchar(30) DEFAULT 'Trader',
  `state` varchar(50) DEFAULT '',
  `district` varchar(50) DEFAULT '',
  `date_of_registration` date DEFAULT NULL,
  `business_nature` varchar(100) DEFAULT '',
  `website` varchar(150) DEFAULT '',
  `city` varchar(80) DEFAULT '',
  `pincode` varchar(12) DEFAULT '',
  `pan_no` varchar(20) DEFAULT '',
  `aadhaar_no` varchar(20) DEFAULT '',
  `state_code` varchar(10) DEFAULT '',
  `tan_no` varchar(20) DEFAULT '',
  `msme_no` varchar(30) DEFAULT '',
  `fssai_no` varchar(30) DEFAULT '',
  `bank_name` varchar(150) DEFAULT '',
  `bank_account_no` varchar(30) DEFAULT '',
  `ifsc_code` varchar(15) DEFAULT '',
  `account_holder_name` varchar(150) DEFAULT '',
  `credit_limit` decimal(14,2) DEFAULT '0.00',
  `default_price_list` varchar(100) DEFAULT '',
  `documents` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `role` enum('owner','admin','manager','accountant','sales','viewer') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'viewer',
  `avatar` text COLLATE utf8_unicode_ci,
  `phone` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `address` text COLLATE utf8_unicode_ci,
  `tags` text COLLATE utf8_unicode_ci,
  `alt_phone` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `license_no` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `license_expiry` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_contacts`
--

CREATE TABLE IF NOT EXISTS `user_contacts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `relation` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_message_log`
--

CREATE TABLE IF NOT EXISTS `wa_message_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `credit_notes`
--
ALTER TABLE `credit_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cn_number` (`cn_number`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `edit_approval_requests`
--
ALTER TABLE `edit_approval_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ear_status` (`status`),
  ADD KEY `idx_ear_entity` (`entity_type`,`entity_id`);

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
-- Indexes for table `payment_vouchers`
--
ALTER TABLE `payment_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payvoucher_date` (`payment_date`);

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
ALTER TABLE `portal_views`
  ADD PRIMARY KEY (`invoice_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `proforma_invoices`
--
ALTER TABLE `proforma_invoices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promise_to_pay`
--
ALTER TABLE `promise_to_pay`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ptp_inv` (`invoice_id`),
  ADD KEY `idx_ptp_date` (`promise_date`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `recurring_schedules`
--
ALTER TABLE `recurring_schedules`
  ADD PRIMARY KEY (`id`);

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
ALTER TABLE `reminder_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_perm` (`role`,`permission_key`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_customer_date` (`customer_id`,`sale_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `smtp_profiles`
--
ALTER TABLE `smtp_profiles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_stockadj_product_date` (`product_id`,`adjustment_date`);

--
-- Indexes for table `stock_in_entries`
--
ALTER TABLE `stock_in_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `stock_in_items`
--
ALTER TABLE `stock_in_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_in_id` (`stock_in_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_product_date` (`product_id`,`movement_date`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_contacts`
--
ALTER TABLE `user_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wa_message_log`
--
ALTER TABLE `wa_message_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_entry_id` (`entry_id`),
  ADD KEY `idx_wa_log_ts` (`ts`),
  ADD KEY `idx_wa_log_inv` (`inv_id`),
  ADD KEY `idx_wa_log_wamid` (`wamid`);

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
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_notes`
--
ALTER TABLE `credit_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `edit_approval_requests`
--
ALTER TABLE `edit_approval_requests`
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
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_vouchers`
--
ALTER TABLE `payment_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portal_tokens`
--
ALTER TABLE `portal_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proforma_invoices`
--
ALTER TABLE `proforma_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promise_to_pay`
--
ALTER TABLE `promise_to_pay`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `smtp_profiles`
--
ALTER TABLE `smtp_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_in_entries`
--
ALTER TABLE `stock_in_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_in_items`
--
ALTER TABLE `stock_in_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_contacts`
--
ALTER TABLE `user_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `recurring_schedule_items`
--
ALTER TABLE `recurring_schedule_items`
  ADD CONSTRAINT `recurring_schedule_items_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `recurring_schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_adjustments_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `stock_in_entries`
--
ALTER TABLE `stock_in_entries`
  ADD CONSTRAINT `stock_in_entries_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `stock_in_items`
--
ALTER TABLE `stock_in_items`
  ADD CONSTRAINT `stock_in_items_ibfk_1` FOREIGN KEY (`stock_in_id`) REFERENCES `stock_in_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_in_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `stock_ledger_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `user_contacts`
--
ALTER TABLE `user_contacts`
  ADD CONSTRAINT `user_contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
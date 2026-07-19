-- ================================================================
--  Migration: add action.* permission keys (MASTER database)
--  Run against your master DB.
--
--  The app's SERVER JS bootstrap (canDo() / delBtn() / assertCanDelete()
--  etc. across every list page) reads action.delete / action.archive /
--  action.edit / action.create / action.approve_edits from the
--  permissions catalog. An earlier migration I gave you speculatively
--  added different, made-up action.* keys before I'd seen your real
--  code — this replaces them with the actual ones the app checks.
--
--  Safe even if you already ran the old migration: ON DUPLICATE KEY
--  UPDATE via the existing uk_key constraint. The old speculative
--  keys (action.invoice.save etc.) are harmless leftover rows if
--  present — nothing in the app reads them — but you can delete them
--  manually if you'd like a clean catalog:
--    DELETE FROM permissions WHERE `key` IN
--      ('action.invoice.save','action.invoice.delete','action.client.delete',
--       'action.product.delete','action.settings.manage','action.reports.view',
--       'action.users.manage');
-- ================================================================

INSERT INTO `permissions` (`key`, `label`, `category`, `default_min_role`, `sort_order`) VALUES
('action.delete',        'Delete Records',        'action', 'manager', 300),
('action.archive',       'Archive Records',       'action', 'manager', 310),
('action.edit',          'Edit Records',          'action', 'sales',   320),
('action.create',        'Create Records',        'action', 'sales',   330),
('action.approve_edits', 'Approve Edit Requests', 'action', 'admin',   340)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`), `category` = VALUES(`category`),
    `default_min_role` = VALUES(`default_min_role`), `sort_order` = VALUES(`sort_order`);

-- ================================================================
--  Migration: role_permissions for action.* keys (TENANT database)
--  Run against each tenant DB (e.g. edrppymy_sneha_enterprises).
--
--  Companion to 005_action_permission_keys_master.sql. Note the app
--  already fails safe if these rows don't exist (canDelete/canEdit/
--  etc. default to true when the key is missing from role_permissions
--  — see the SERVER bootstrap comment in layout_header.php), so this
--  isn't as urgent as the earlier menu.* migration, but real rows let
--  you actually restrict these actions per role rather than relying
--  on the default-allow fallback.
-- ================================================================

INSERT INTO `role_permissions` (`role`, `permission_key`, `enabled`) VALUES
('admin',      'action.delete', 1), ('manager',    'action.delete', 1),
('accountant', 'action.delete', 0), ('sales',      'action.delete', 0), ('viewer', 'action.delete', 0),
('admin',      'action.archive', 1), ('manager',    'action.archive', 1),
('accountant', 'action.archive', 0), ('sales',      'action.archive', 0), ('viewer', 'action.archive', 0),
('admin',      'action.edit', 1), ('manager',    'action.edit', 1),
('accountant', 'action.edit', 1), ('sales',      'action.edit', 1), ('viewer', 'action.edit', 0),
('admin',      'action.create', 1), ('manager',    'action.create', 1),
('accountant', 'action.create', 1), ('sales',      'action.create', 1), ('viewer', 'action.create', 0),
('admin',      'action.approve_edits', 1), ('manager',    'action.approve_edits', 0),
('accountant', 'action.approve_edits', 0), ('sales',      'action.approve_edits', 0), ('viewer', 'action.approve_edits', 0)
ON DUPLICATE KEY UPDATE `enabled` = VALUES(`enabled`);

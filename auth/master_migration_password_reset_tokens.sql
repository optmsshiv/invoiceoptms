-- ================================================================
--  Run this against the MASTER DB only (optms_master or whatever
--  MASTER_DB_NAME resolves to in .env) — NOT any tenant database.
--  Password reset tokens live here because email→user lookup for
--  a not-yet-authenticated visitor has to happen against the
--  master `users` table, same as attemptLogin() in auth.php.
-- ================================================================

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,   -- sha256 of the emailed token; plaintext is never stored
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token_hash (token_hash),
  KEY idx_user_id (user_id),
  CONSTRAINT fk_password_reset_tokens_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

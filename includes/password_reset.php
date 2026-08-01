<?php
// ================================================================
//  OPTMS Invoice Manager — includes/password_reset.php
//  Forgot-password token issuing + lookup.
//
//  Password resets happen BEFORE any tenant is known, so — same as
//  attemptLogin() in auth.php — everything here runs against the
//  MASTER DB (users + tenants), never getDB() (tenant DB).
//
//  Requires: config/db.php (getMasterDB, env), includes/mailer.php
// ================================================================
require_once __DIR__ . '/mailer.php';

const PASSWORD_RESET_TOKEN_TTL_MINUTES = 60; // link validity window

// ── Look up a user eligible for password reset (master DB) ────────
// Mirrors the WHERE clause used in attemptLogin(), minus the password
// check. Returns null (not false) so callers can `if ($user)` safely.
function findUserForPasswordReset(string $email): ?array {
    try {
        $stmt = getMasterDB()->prepare(
            'SELECT u.id, u.name, u.email, u.tenant_id, t.company_name
               FROM users u
               LEFT JOIN tenants t ON t.id = u.tenant_id
              WHERE u.email = ? AND u.status = "active"'
        );
        $stmt->execute([trim($email)]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        error_log('findUserForPasswordReset error: ' . $e->getMessage());
        return null;
    }
}

// ── Generate a token, store its hash, and email the reset link ────
// The PLAINTEXT token only ever exists in the URL we email out; the
// DB only ever stores a sha256 hash of it, so a DB leak alone can't
// be used to reset anyone's password.
function issuePasswordResetAndEmail(array $user): bool {
    try {
        $master = getMasterDB();

        // Invalidate any earlier unused tokens for this user so only
        // the most recent reset link is ever valid.
        $master->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW()
              WHERE user_id = ? AND used_at IS NULL'
        )->execute([$user['id']]);

        $token     = bin2hex(random_bytes(32)); // 64-char plaintext token
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TOKEN_TTL_MINUTES * 60);

        $master->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        )->execute([$user['id'], $tokenHash, $expiresAt, $_SERVER['REMOTE_ADDR'] ?? '']);

        $resetLink = rtrim(APP_URL, '/') . '/auth/reset_password.php?token=' . $token;

        sendPasswordResetEmail(
            $user['email'],
            $user['name'],
            $resetLink,
            $user['company_name'] ?? APP_NAME,
            PASSWORD_RESET_TOKEN_TTL_MINUTES
        );

        masterAuditLog($user['id'], $user['tenant_id'], 'password_reset_request', 'Password reset link emailed');
        return true;

    } catch (Exception $e) {
        // Never let a failure here surface to the client — the caller
        // always reports the same "if registered..." success message
        // regardless, to avoid email enumeration.
        error_log('issuePasswordResetAndEmail error: ' . $e->getMessage());
        return false;
    }
}

// ── Validate a plaintext token from the reset link (master DB) ────
// Returns the associated user row, or null if invalid/expired/used.
function validatePasswordResetToken(string $token): ?array {
    if ($token === '') return null;
    try {
        $tokenHash = hash('sha256', $token);
        $master    = getMasterDB();
        $stmt = $master->prepare(
            'SELECT prt.id AS token_id, u.id, u.name, u.email, u.tenant_id
               FROM password_reset_tokens prt
               JOIN users u ON u.id = prt.user_id
              WHERE prt.token_hash = ?
                AND prt.used_at IS NULL
                AND prt.expires_at > NOW()
                AND u.status = "active"'
        );
        $stmt->execute([$tokenHash]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        error_log('validatePasswordResetToken error: ' . $e->getMessage());
        return null;
    }
}

// ── Consume a token: set the new password, mark token used ────────
function completePasswordReset(int $tokenId, int $userId, string $newPassword): bool {
    try {
        $master = getMasterDB();
        $master->beginTransaction();

        $master->prepare('UPDATE users SET password = ? WHERE id = ?')
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        $master->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')
               ->execute([$tokenId]);

        // Any other outstanding tokens for this user are now moot.
        $master->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW()
              WHERE user_id = ? AND used_at IS NULL'
        )->execute([$userId]);

        $master->commit();
        masterAuditLog($userId, null, 'password_reset_complete', 'Password reset via emailed link');
        return true;
    } catch (Exception $e) {
        if ($master->inTransaction()) $master->rollBack();
        error_log('completePasswordReset error: ' . $e->getMessage());
        return false;
    }
}

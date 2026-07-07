<?php
// ================================================================
//  OPTMS Invoice Manager — includes/session_unlock.php
//  Called by the lock-screen overlay's password form via fetch().
//  Verifies the current user's password and, if correct, unlocks
//  the existing session in place (no re-login, no page reload).
// ================================================================
require_once __DIR__ . '/auth.php';

startSession();

if (empty($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Not authenticated', 'redirect' => '/auth/login.php'], 401);
}

$password = $_POST['password'] ?? '';
if ($password === '') {
    jsonResponse(['ok' => false, 'reason' => 'empty_password'], 400);
}

$result = unlockSession($password);

if ($result['ok']) {
    jsonResponse(['ok' => true]);
}

$httpCode = ($result['reason'] === 'too_many_attempts') ? 403 : 401;
jsonResponse($result, $httpCode);

<?php
// ================================================================
//  OPTMS Invoice Manager — includes/session_lock.php
//  Called by session-timeout.js the moment the client-side idle
//  countdown reaches zero. Locks the session immediately server-side
//  (rather than waiting for the next request to naturally get denied).
//
//  Deliberately does NOT call requireLogin() — that would immediately
//  hit the lock check we're trying to set. Locking should succeed even
//  if the session is already past SESSION_LIFETIME.
// ================================================================
require_once __DIR__ . '/auth.php';

startSession();

if (empty($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

lockSession();

jsonResponse(['status' => 'locked']);

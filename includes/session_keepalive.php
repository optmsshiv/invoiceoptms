<?php
// ================================================================
//  OPTMS Invoice Manager — includes/session_keepalive.php
//  Called via fetch() by assets/js/session-timeout.js while the
//  user is actively working, so that a long-lived but genuinely
//  active session doesn't get logged out due to no page navigation.
//
//  Also called when the user clicks "Stay logged in" on the
//  expiry-warning modal.
// ================================================================
require_once __DIR__ . '/auth.php';

// requireLogin() does the real work:
//  - resets $_SESSION['last_activity']
//  - re-issues the session cookie with a fresh expiry
//  - if the session has ALREADY expired, it will itself call
//    doLogout() + _authFail(), which returns a 401 JSON response
//    (since this endpoint is under /includes/ but always called
//    via fetch/XHR — make sure your frontend sends
//    X-Requested-With: XMLHttpRequest, see session-timeout.js)
requireLogin();

jsonResponse([
    'status'     => 'ok',
    'expires_in' => SESSION_LIFETIME,
    'server_time'=> time(),
]);

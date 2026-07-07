/**
 * OPTMS Invoice Manager — Session Timeout Handler
 * ================================================================
 * Include this on every authenticated page:
 *
 *   <?php renderSessionTimeoutAssets(); ?>
 *
 * Behavior:
 *  - Tracks real user activity (mouse/keyboard/scroll/touch).
 *  - While active, pings the server periodically to keep the
 *    session + cookie alive (fixes "active but still logged out").
 *  - Shows a warning modal `warningSecs` before the idle deadline.
 *  - "Stay logged in" -> pings server, resets timers.
 *  - Idle deadline reached -> LOCKS the session (not a hard logout):
 *      a full-screen overlay asks for the password to resume, right
 *      where the user left off — no navigation, no lost form data.
 *  - Too many wrong password attempts -> real logout, redirect to login.
 *  - Synced across tabs via localStorage: one tab locking/unlocking
 *    locks/unlocks all open tabs.
 * ================================================================
 */
(function () {
    'use strict';

    var cfg = Object.assign({
        lifetime: 7200,
        warningSecs: 120,
        keepaliveUrl: '/includes/session_keepalive.php',
        lockUrl: '/includes/session_lock.php',
        unlockUrl: '/includes/session_unlock.php',
        loginUrl: '/auth/login.php',
        logoutUrl: '/auth/logout.php',
        userName: '',
        pingIntervalSecs: 300 // don't hit the server more than once per 5 min while active
    }, window.OPTMS_SESSION_CONFIG || {});

    var STORAGE_KEY = 'optms_last_activity';
    var LOCK_KEY    = 'optms_locked';
    var LOGOUT_KEY  = 'optms_force_logout';

    var lastLocalActivity = Date.now();
    var lastServerPing     = Date.now();
    var warningModalEl     = null;
    var lockOverlayEl      = null;
    var countdownTimer     = null;
    var tickTimer          = null;
    var locked             = false;

    function now() { return Date.now(); }

    function getLastActivity() {
        var stored = parseInt(localStorage.getItem(STORAGE_KEY), 10);
        return isNaN(stored) ? lastLocalActivity : Math.max(stored, lastLocalActivity);
    }

    function setLastActivity(ts) {
        lastLocalActivity = ts;
        try { localStorage.setItem(STORAGE_KEY, String(ts)); } catch (e) {}
    }

    function secondsSinceLastActivity() {
        return Math.floor((now() - getLastActivity()) / 1000);
    }

    function isLockedLocally() {
        try { return localStorage.getItem(LOCK_KEY) === '1'; } catch (e) { return locked; }
    }

    function setLockedFlag(val) {
        locked = val;
        try {
            if (val) localStorage.setItem(LOCK_KEY, '1');
            else localStorage.removeItem(LOCK_KEY);
        } catch (e) {}
    }

    // ---- Keep-alive ping to the server -----------------------------
    function pingServer(onDone) {
        fetch(cfg.keepaliveUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (res) {
            if (res.status === 423) { showLockScreen(); if (onDone) onDone(false); return; }
            if (res.status === 401) { forceLogout(); return; }
            lastServerPing = now();
            if (onDone) onDone(true);
        }).catch(function () {
            if (onDone) onDone(false);
        });
    }

    // ---- Proactively lock the server session the moment we hit 0 ------
    function lockServerSession(onDone) {
        fetch(cfg.lockUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function () { if (onDone) onDone(); }).catch(function () { if (onDone) onDone(); });
    }

    // ---- Warning modal (before lock) -----------------------------------
    function buildWarningModal() {
        if (warningModalEl) return warningModalEl;

        var overlay = document.createElement('div');
        overlay.id = 'optms-warning-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.cssText = baseOverlayStyle();

        var box = document.createElement('div');
        box.style.cssText = baseBoxStyle();

        var title = document.createElement('h2');
        title.textContent = "You're about to be locked out";
        title.style.cssText = 'margin:0 0 12px;font-size:18px;color:#1a1a1a';

        var msg = document.createElement('p');
        msg.id = 'optms-warning-msg';
        msg.style.cssText = 'margin:0 0 20px;color:#555;font-size:14px;line-height:1.5';

        var btnRow = document.createElement('div');
        btnRow.style.cssText = 'display:flex;gap:10px;justify-content:center';

        var stayBtn = primaryButton('Stay logged in');
        stayBtn.onclick = function () {
            stayBtn.disabled = true;
            stayBtn.textContent = 'Renewing…';
            pingServer(function (ok) {
                if (ok) {
                    setLastActivity(now());
                    hideWarningModal();
                } else {
                    stayBtn.disabled = false;
                    stayBtn.textContent = 'Try again';
                }
            });
        };

        var lockNowBtn = secondaryButton('Lock now');
        lockNowBtn.onclick = function () { triggerLock(); };

        btnRow.appendChild(stayBtn);
        btnRow.appendChild(lockNowBtn);
        box.appendChild(title);
        box.appendChild(msg);
        box.appendChild(btnRow);
        overlay.appendChild(box);

        warningModalEl = overlay;
        return overlay;
    }

    function showWarningModal(secondsLeft) {
        if (isLockedLocally()) return;
        var overlay = buildWarningModal();
        if (!document.body.contains(overlay)) document.body.appendChild(overlay);
        updateWarningCountdown(secondsLeft);
        overlay.style.display = 'flex';

        clearInterval(countdownTimer);
        countdownTimer = setInterval(function () {
            var remaining = cfg.lifetime - secondsSinceLastActivity();
            if (remaining <= 0) {
                clearInterval(countdownTimer);
                triggerLock();
                return;
            }
            updateWarningCountdown(remaining);
        }, 1000);
    }

    function updateWarningCountdown(secondsLeft) {
        var msg = document.getElementById('optms-warning-msg');
        if (!msg) return;
        var m = Math.floor(secondsLeft / 60);
        var s = secondsLeft % 60;
        var timeStr = m + ':' + (s < 10 ? '0' : '') + s;
        msg.textContent = "You've been inactive for a while. Your session will lock in " +
            timeStr + ' — enter your password to resume, or stay active now.';
    }

    function hideWarningModal() {
        if (warningModalEl) warningModalEl.style.display = 'none';
        clearInterval(countdownTimer);
    }

    // ---- Lock overlay ----------------------------------------------------
    function buildLockOverlay() {
        if (lockOverlayEl) return lockOverlayEl;

        var overlay = document.createElement('div');
        overlay.id = 'optms-lock-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.cssText = baseOverlayStyle(true);

        var box = document.createElement('div');
        box.style.cssText = baseBoxStyle();

        var iconWrap = document.createElement('div');
        iconWrap.style.cssText = 'width:56px;height:56px;border-radius:50%;background:#085041;' +
            'display:flex;align-items:center;justify-content:center;margin:0 auto 18px';
        iconWrap.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' +
            'xmlns="http://www.w3.org/2000/svg"><path d="M6 10V8a6 6 0 1112 0v2M5 10h14a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1v-9a1 1 0 011-1z" ' +
            'stroke="#9FE1CB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        var title = document.createElement('h2');
        title.textContent = 'Session locked';
        title.style.cssText = 'margin:0 0 6px;font-size:19px;color:#111827';

        var sub = document.createElement('p');
        sub.textContent = (cfg.userName ? 'Welcome back, ' + cfg.userName + '. ' : '') +
            'Enter your password to continue.';
        sub.style.cssText = 'margin:0 0 22px;font-size:13px;color:#6B7280';

        var errMsg = document.createElement('div');
        errMsg.id = 'optms-lock-error';
        errMsg.style.cssText = 'display:none;background:#FEF2F2;border:1px solid #FECACA;' +
            'border-radius:9px;padding:10px 14px;font-size:13px;color:#B91C1C;margin-bottom:16px;text-align:left';

        var form = document.createElement('form');
        form.style.cssText = 'text-align:left';
        form.onsubmit = function (e) {
            e.preventDefault();
            attemptUnlock();
        };

        var label = document.createElement('label');
        label.textContent = 'Password';
        label.style.cssText = 'font-size:11.5px;font-weight:700;color:#6B7280;text-transform:uppercase;' +
            'letter-spacing:.45px;margin-bottom:5px;display:block';

        var input = document.createElement('input');
        input.type = 'password';
        input.id = 'optms-lock-password';
        input.placeholder = '••••••••';
        input.autocomplete = 'current-password';
        input.style.cssText = 'width:100%;padding:11px 12px;border:1.5px solid #E5E7EB;border-radius:9px;' +
            'font-family:inherit;font-size:14px;color:#111;background:#F9FAFB;outline:none;margin-bottom:16px';

        var unlockBtn = primaryButton('Unlock');
        unlockBtn.type = 'submit';
        unlockBtn.style.width = '100%';
        unlockBtn.style.marginBottom = '12px';

        form.appendChild(label);
        form.appendChild(input);
        form.appendChild(unlockBtn);

        var logoutLink = document.createElement('a');
        logoutLink.textContent = 'Not you? Log out';
        logoutLink.href = cfg.logoutUrl;
        logoutLink.style.cssText = 'font-size:12.5px;color:#6B7280;text-decoration:none;cursor:pointer';
        logoutLink.onmouseenter = function () { logoutLink.style.textDecoration = 'underline'; };
        logoutLink.onclick = function (e) { e.preventDefault(); forceLogout(); };

        box.appendChild(iconWrap);
        box.appendChild(title);
        box.appendChild(sub);
        box.appendChild(errMsg);
        box.appendChild(form);
        box.appendChild(logoutLink);
        overlay.appendChild(box);

        lockOverlayEl = overlay;
        return overlay;
    }

    function attemptUnlock() {
        var input = document.getElementById('optms-lock-password');
        var errBox = document.getElementById('optms-lock-error');
        var btn = lockOverlayEl.querySelector('button[type="submit"]');
        var password = input.value;

        if (!password) return;

        btn.disabled = true;
        btn.textContent = 'Unlocking…';
        errBox.style.display = 'none';

        fetch(cfg.unlockUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: 'password=' + encodeURIComponent(password)
        }).then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
          .then(function (r) {
            if (r.data && r.data.ok) {
                setLockedFlag(false);
                setLastActivity(now());
                hideLockScreen();
                input.value = '';
            } else if (r.data && r.data.reason === 'too_many_attempts') {
                forceLogout();
            } else {
                var left = (r.data && typeof r.data.attempts_left === 'number') ? r.data.attempts_left : null;
                errBox.textContent = 'Incorrect password.' + (left !== null ? ' ' + left + ' attempt(s) left.' : '');
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Unlock';
                input.value = '';
                input.focus();
            }
        }).catch(function () {
            errBox.textContent = 'Network error — please try again.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Unlock';
        });
    }

    function showLockScreen() {
        if (locked) return;
        setLockedFlag(true);
        hideWarningModal();
        var overlay = buildLockOverlay();
        if (!document.body.contains(overlay)) document.body.appendChild(overlay);
        overlay.style.display = 'flex';
        setTimeout(function () {
            var input = document.getElementById('optms-lock-password');
            if (input) input.focus();
        }, 50);
    }

    function hideLockScreen() {
        locked = false;
        if (lockOverlayEl) lockOverlayEl.style.display = 'none';
    }

    function triggerLock() {
        lockServerSession(function () { showLockScreen(); });
    }

    // ---- Shared styling helpers -----------------------------------------
    function baseOverlayStyle(opaque) {
        return [
            'position:fixed', 'inset:0',
            'background:' + (opaque ? 'rgba(8,80,65,0.35)' : 'rgba(0,0,0,0.5)'),
            'display:flex', 'align-items:center', 'justify-content:center',
            'z-index:999999', 'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif',
            'backdrop-filter:blur(3px)'
        ].join(';');
    }
    function baseBoxStyle() {
        return [
            'background:#fff', 'border-radius:16px', 'padding:32px 34px',
            'max-width:380px', 'width:90%', 'box-shadow:0 12px 56px rgba(8,80,65,0.18)',
            'text-align:center'
        ].join(';');
    }
    function primaryButton(text) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = text;
        b.style.cssText = 'background:#085041;color:#E1F5EE;border:none;padding:11px 18px;' +
            'border-radius:9px;font-size:14px;cursor:pointer;font-weight:700';
        b.onmouseenter = function () { b.style.background = '#0F6E56'; };
        b.onmouseleave = function () { b.style.background = '#085041'; };
        return b;
    }
    function secondaryButton(text) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = text;
        b.style.cssText = 'background:#f3f4f6;color:#374151;border:none;padding:11px 18px;' +
            'border-radius:9px;font-size:14px;cursor:pointer';
        return b;
    }

    // ---- Cross-tab sync ------------------------------------------------
    function forceLogout() {
        try { localStorage.setItem(LOGOUT_KEY, String(now())); } catch (e) {}
        // Hit the real logout endpoint so the server session is actually
        // destroyed (doLogout()) — redirecting straight to loginUrl would
        // just bounce back to the lock screen, since the session/user_id
        // would still be intact server-side.
        window.location.href = cfg.logoutUrl;
    }

    window.addEventListener('storage', function (e) {
        if (e.key === LOGOUT_KEY) {
            window.location.href = cfg.logoutUrl;
        }
        if (e.key === LOCK_KEY) {
            if (e.newValue === '1') { locked = true; hideWarningModal(); showLockScreenUiOnly(); }
            else { hideLockScreen(); }
        }
        if (e.key === STORAGE_KEY) {
            lastLocalActivity = parseInt(e.newValue, 10) || lastLocalActivity;
            if (!isLockedLocally()) hideWarningModal();
        }
    });

    // Show the overlay in a tab that got locked by ANOTHER tab (skip the
    // redundant lockServerSession() call — it's already locked server-side).
    function showLockScreenUiOnly() {
        var overlay = buildLockOverlay();
        if (!document.body.contains(overlay)) document.body.appendChild(overlay);
        overlay.style.display = 'flex';
    }

    // ---- Activity tracking ---------------------------------------------
    var activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
    var throttleTimer = null;

    function onActivity() {
        if (isLockedLocally()) return; // don't treat typing the unlock password as "activity" that bypasses the lock
        if (throttleTimer) return;
        throttleTimer = setTimeout(function () { throttleTimer = null; }, 5000);

        setLastActivity(now());
        hideWarningModal();

        if ((now() - lastServerPing) / 1000 >= cfg.pingIntervalSecs) {
            pingServer();
        }
    }

    activityEvents.forEach(function (evt) {
        window.addEventListener(evt, onActivity, { passive: true });
    });

    // ---- Master tick -----------------------------------------------------
    tickTimer = setInterval(function () {
        if (isLockedLocally()) {
            if (!locked) { locked = true; showLockScreenUiOnly(); }
            return;
        }
        var elapsed = secondsSinceLastActivity();
        var remaining = cfg.lifetime - elapsed;

        if (remaining <= 0) {
            triggerLock();
        } else if (remaining <= cfg.warningSecs) {
            showWarningModal(remaining);
        }
    }, 5000);

    // Initialize
    setLastActivity(getLastActivity());
    if (isLockedLocally()) { locked = true; showLockScreenUiOnly(); }
})();

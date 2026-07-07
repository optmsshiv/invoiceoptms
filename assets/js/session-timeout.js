/**
 * OPTMS Invoice Manager — Session Timeout Handler
 * ================================================================
 * Include this on every authenticated page:
 *
 *   <script>
 *     window.OPTMS_SESSION_CONFIG = {
 *       lifetime:    <?= SESSION_LIFETIME ?>,      // seconds, e.g. 7200
 *       warningSecs: 120,                           // show modal this long before expiry
 *       keepaliveUrl: '/includes/session_keepalive.php',
 *       loginUrl:    '/auth/login.php'
 *     };
 *   </script>
 *   <script src="/assets/js/session-timeout.js"></script>
 *
 * Behavior:
 *  - Tracks real user activity (mouse/keyboard/scroll/touch).
 *  - While active, pings the server periodically to keep the
 *    session + cookie alive (fixes "active but still logged out").
 *  - Shows a warning modal `warningSecs` before expiry if idle.
 *  - "Stay logged in" -> pings server, resets timers.
 *  - Timeout reached -> redirects to loginUrl.
 *  - Synced across tabs via localStorage, so one active tab keeps
 *    all tabs alive, and one idle timeout logs out all tabs.
 * ================================================================
 */
(function () {
    'use strict';

    var cfg = Object.assign({
        lifetime: 7200,
        warningSecs: 120,
        keepaliveUrl: '/includes/session_keepalive.php',
        loginUrl: '/auth/login.php',
        pingIntervalSecs: 300 // don't hit the server more than once per 5 min while active
    }, window.OPTMS_SESSION_CONFIG || {});

    var STORAGE_KEY = 'optms_last_activity';
    var LOGOUT_KEY  = 'optms_force_logout';

    var lastLocalActivity = Date.now();
    var lastServerPing     = Date.now();
    var modalEl            = null;
    var countdownTimer     = null;
    var tickTimer          = null;

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

    // ---- Keep-alive ping to the server -----------------------------
    function pingServer(onDone) {
        fetch(cfg.keepaliveUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(function (res) {
            if (res.status === 401) {
                forceLogout();
                return;
            }
            lastServerPing = now();
            if (typeof onDone === 'function') onDone(true);
        }).catch(function () {
            // Network hiccup — don't log the user out on a single failed ping,
            // the next activity tick or the local countdown will retry / catch up.
            if (typeof onDone === 'function') onDone(false);
        });
    }

    // ---- Modal UI ----------------------------------------------------
    function buildModal() {
        if (modalEl) return modalEl;

        var overlay = document.createElement('div');
        overlay.id = 'optms-session-modal-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.cssText = [
            'position:fixed', 'inset:0', 'background:rgba(0,0,0,0.5)',
            'display:flex', 'align-items:center', 'justify-content:center',
            'z-index:999999', 'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif'
        ].join(';');

        var box = document.createElement('div');
        box.style.cssText = [
            'background:#fff', 'border-radius:10px', 'padding:28px 32px',
            'max-width:380px', 'width:90%', 'box-shadow:0 10px 40px rgba(0,0,0,0.25)',
            'text-align:center'
        ].join(';');

        var title = document.createElement('h2');
        title.textContent = "You're about to be logged out";
        title.style.cssText = 'margin:0 0 12px;font-size:18px;color:#1a1a1a';

        var msg = document.createElement('p');
        msg.id = 'optms-session-modal-msg';
        msg.style.cssText = 'margin:0 0 20px;color:#555;font-size:14px;line-height:1.5';

        var btnRow = document.createElement('div');
        btnRow.style.cssText = 'display:flex;gap:10px;justify-content:center';

        var stayBtn = document.createElement('button');
        stayBtn.textContent = 'Stay logged in';
        stayBtn.style.cssText = [
            'background:#2563eb', 'color:#fff', 'border:none', 'padding:10px 18px',
            'border-radius:6px', 'font-size:14px', 'cursor:pointer', 'font-weight:600'
        ].join(';');
        stayBtn.onmouseenter = function () { stayBtn.style.background = '#1d4ed8'; };
        stayBtn.onmouseleave = function () { stayBtn.style.background = '#2563eb'; };
        stayBtn.onclick = function () {
            stayBtn.disabled = true;
            stayBtn.textContent = 'Renewing…';
            pingServer(function (ok) {
                if (ok) {
                    setLastActivity(now());
                    broadcastActivity();
                    hideModal();
                } else {
                    stayBtn.disabled = false;
                    stayBtn.textContent = 'Try again';
                }
            });
        };

        var logoutBtn = document.createElement('button');
        logoutBtn.textContent = 'Log out now';
        logoutBtn.style.cssText = [
            'background:#f3f4f6', 'color:#374151', 'border:none', 'padding:10px 18px',
            'border-radius:6px', 'font-size:14px', 'cursor:pointer'
        ].join(';');
        logoutBtn.onclick = function () { window.location.href = cfg.loginUrl; };

        btnRow.appendChild(stayBtn);
        btnRow.appendChild(logoutBtn);
        box.appendChild(title);
        box.appendChild(msg);
        box.appendChild(btnRow);
        overlay.appendChild(box);

        modalEl = overlay;
        return overlay;
    }

    function showModal(secondsLeft) {
        var overlay = buildModal();
        if (!document.body.contains(overlay)) document.body.appendChild(overlay);
        updateModalCountdown(secondsLeft);
        overlay.style.display = 'flex';

        clearInterval(countdownTimer);
        countdownTimer = setInterval(function () {
            var remaining = cfg.lifetime - secondsSinceLastActivity();
            if (remaining <= 0) {
                clearInterval(countdownTimer);
                forceLogout();
                return;
            }
            updateModalCountdown(remaining);
        }, 1000);
    }

    function updateModalCountdown(secondsLeft) {
        var msg = document.getElementById('optms-session-modal-msg');
        if (!msg) return;
        var m = Math.floor(secondsLeft / 60);
        var s = secondsLeft % 60;
        var timeStr = m + ':' + (s < 10 ? '0' : '') + s;
        msg.textContent = "You've been inactive for a while. For your security, you'll be " +
            'logged out in ' + timeStr + ' unless you choose to stay.';
    }

    function hideModal() {
        if (modalEl) modalEl.style.display = 'none';
        clearInterval(countdownTimer);
    }

    // ---- Cross-tab sync ----------------------------------------------
    function broadcastActivity() {
        try { localStorage.setItem(STORAGE_KEY, String(now())); } catch (e) {}
    }

    function forceLogout() {
        try { localStorage.setItem(LOGOUT_KEY, String(now())); } catch (e) {}
        window.location.href = cfg.loginUrl + (cfg.loginUrl.indexOf('?') === -1 ? '?' : '&') + 'expired=1';
    }

    window.addEventListener('storage', function (e) {
        if (e.key === LOGOUT_KEY) {
            window.location.href = cfg.loginUrl + (cfg.loginUrl.indexOf('?') === -1 ? '?' : '&') + 'expired=1';
        }
        if (e.key === STORAGE_KEY) {
            lastLocalActivity = parseInt(e.newValue, 10) || lastLocalActivity;
            hideModal();
        }
    });

    // ---- Activity tracking ---------------------------------------------
    var activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
    var throttleTimer = null;

    function onActivity() {
        if (throttleTimer) return;
        throttleTimer = setTimeout(function () { throttleTimer = null; }, 5000); // throttle to 1x/5s

        setLastActivity(now());
        hideModal();

        // Real activity happened — refresh server session if we haven't
        // pinged recently, so long working sessions on one page don't expire.
        if ((now() - lastServerPing) / 1000 >= cfg.pingIntervalSecs) {
            pingServer();
        }
    }

    activityEvents.forEach(function (evt) {
        window.addEventListener(evt, onActivity, { passive: true });
    });

    // ---- Master tick: decide whether to show the warning --------------
    tickTimer = setInterval(function () {
        var elapsed = secondsSinceLastActivity();
        var remaining = cfg.lifetime - elapsed;

        if (remaining <= 0) {
            forceLogout();
        } else if (remaining <= cfg.warningSecs) {
            showModal(remaining);
        }
    }, 5000);

    // Initialize
    setLastActivity(getLastActivity());
})();

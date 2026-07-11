<?php
// ================================================================
//  pages/comms/msglog.php
//  WhatsApp Message Log — read-only history + resend/export.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.msglog');

$user = currentUser();

$activePage  = 'msglog';
$pageTitle   = 'Message Log';
$pageScripts = ['/assets/js/shared-data.js', '/assets/js/wa-shared.js', '/assets/js/msglog.js'];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <style>
      /* ── WhatsApp Log Table Styling ── */
      .wa-log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
      .wa-log-table thead tr { background: var(--bg); border-bottom: 2px solid var(--border); }
      .wa-log-table th {
        padding: 10px 14px; text-align: left; font-weight: 700;
        color: var(--muted); font-size: 11px; text-transform: uppercase;
        letter-spacing: .5px;
      }
      .wa-log-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
      .wa-log-table tbody tr:hover { background: rgba(0,137,123,.02); }

      .wa-log-ts {
        font-family: var(--mono); font-size: 12px; color: var(--muted);
        white-space: nowrap;
      }

      .wa-log-msg {
        font-size: 12px; color: var(--text2);
        max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      }

      .wa-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
      }
      .wa-badge-sending { background: var(--amber-bg); color: var(--amber); }
      .wa-badge-sent_web { background: var(--blue-bg); color: var(--blue); }
      .wa-badge-sent_api { background: var(--green-bg); color: var(--green); }
      .wa-badge-failed { background: var(--red-bg); color: var(--red); }

      .wa-stat-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 8px; padding: 12px 16px;
        display: flex; align-items: center; gap: 10px; min-width: 160px;
      }
      .wa-stat-icon {
        font-size: 20px; width: 40px; height: 40px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 8px;
      }
      .wa-stat-content { flex: 1; }
      .wa-stat-label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; }
      .wa-stat-value { font-size: 18px; font-weight: 700; color: var(--text); margin-top: 2px; }
    </style>

      <div class="page-toolbar">
        <div class="toolbar-left">
          <input
            id="msglog-search"
            type="text"
            placeholder="Search by client, phone, invoice…"
            style="padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;width:260px;background:var(--card);color:var(--text)"
            oninput="renderWALog()"
          >
          <select
            id="msglog-filter-type"
            style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--card);color:var(--text)"
            onchange="renderWALog()"
          >
            <option value="">📋 All Types</option>
            <option value="invoice_created">📄 New Invoice</option>
            <option value="estimate_created">📋 Estimate</option>
            <option value="payment_received">✅ Payment Receipt</option>
            <option value="partial_payment">💛 Partial Payment</option>
            <option value="split_payment">⚡ Split Payment</option>
            <option value="payment_overdue">🔴 Overdue Alert</option>
            <option value="payment_reminder">🔔 Due Reminder</option>
            <option value="invoice_followup">📞 Follow-up</option>
          </select>
          <select
            id="msglog-filter-status"
            style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--card);color:var(--text)"
            onchange="renderWALog()"
          >
            <option value="">📊 All Status</option>
            <option value="sending">⏳ Sending</option>
            <option value="sent_api">✅ Sent (API)</option>
            <option value="sent_web">📱 Opened (Manual)</option>
            <option value="failed">❌ Failed</option>
          </select>
        </div>
        <div class="toolbar-right">
          <span id="wa-log-last-refresh" style="font-size:11px;color:var(--muted);align-self:center;white-space:nowrap"></span>
          <button class="btn btn-outline" id="wa-log-refresh-btn" onclick="renderWALog(true)" title="Refresh log"><i class="fas fa-sync-alt"></i> Refresh</button>
          <button class="btn btn-outline" onclick="exportMsgLog()"><i class="fas fa-download"></i> Export</button>
          <button class="btn btn-outline" style="color:#E53935;border-color:#E53935" onclick="WA_LOG.clearLogs()"><i class="fas fa-trash"></i> Clear Log</button>
        </div>
      </div>

      <!-- Statistics Row -->
      <div style="display:flex;gap:12px;flex-wrap:wrap;padding:0 0 16px;margin-bottom:8px" id="wa-log-stats">
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--blue-bg);color:var(--blue)"><i class="fas fa-envelope"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Total Messages</div>
            <div class="wa-stat-value" id="wa-stat-total">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--green-bg);color:var(--green)"><i class="fas fa-check-circle"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Sent (API)</div>
            <div class="wa-stat-value" id="wa-stat-sent">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--blue-bg);color:var(--blue)"><i class="fas fa-mobile-alt"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Manual</div>
            <div class="wa-stat-value" id="wa-stat-manual">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--amber-bg);color:var(--amber)"><i class="fas fa-hourglass-half"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Sending</div>
            <div class="wa-stat-value" id="wa-stat-sending">0</div>
          </div>
        </div>
        <div class="wa-stat-card">
          <div class="wa-stat-icon" style="background:var(--red-bg);color:var(--red)"><i class="fas fa-times-circle"></i></div>
          <div class="wa-stat-content">
            <div class="wa-stat-label">Failed</div>
            <div class="wa-stat-value" id="wa-stat-failed">0</div>
          </div>
        </div>
      </div>

      <!-- Log table -->
      <div style="background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden">
        <table id="wa-log-table" class="wa-log-table">
          <thead>
            <tr>
              <th style="width:130px">Time</th>
              <th style="width:120px">Type</th>
              <th style="width:150px">Client</th>
              <th style="width:120px">Invoice</th>
              <th style="width:160px">Message</th>
              <th style="width:100px">Status</th>
              <th style="width:90px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">
                <i class="fas fa-whatsapp" style="font-size:40px;color:#25D366;opacity:0.3;display:block;margin-bottom:8px"></i>
                <div>No messages logged yet</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>

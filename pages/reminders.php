<?php
// ================================================================
//  pages/reminders.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.reminders');
$user = currentUser();

$activePage = 'reminders';
$pageTitle  = 'Reminders';
require_once __DIR__ . '/../includes/layout_header.php';
?>

      <div style="display:flex;gap:16px;align-items:stretch;margin-bottom:18px;flex-wrap:wrap">
        <div class="dash-card" style="flex:0 0 300px;min-width:260px">
          <div class="card-header"><span class="card-title"><i class="fas fa-cog" style="color:var(--teal)"></i> Reminder Rules</span></div>
          <div style="display:flex;flex-direction:column;gap:10px">
            <div class="field"><label>Send reminder before due date (days)</label>
              <input type="number" id="rem-before-days" value="<?= htmlspecialchars($settings['before_days'] ?? '3') ?>" min="1" max="30" style="width:100%">
            </div>
            <div class="field"><label>Send reminder on due date</label>
              <select id="rem-on-due" style="width:100%"><option value="1"<?= (($settings['on_due']??'1')==='1')?' selected':'' ?>>Yes</option><option value="0"<?= (($settings['on_due']??'1')==='0')?' selected':'' ?>>No</option></select>
            </div>
            <div class="field"><label>Send overdue reminder every (days)</label>
              <input type="number" id="rem-overdue-freq" value="<?= htmlspecialchars($settings['overdue_freq'] ?? '7') ?>" min="1" max="30" style="width:100%">
            </div>
            <div class="field"><label>Max overdue reminders</label>
              <input type="number" id="rem-max-overdue" value="<?= htmlspecialchars($settings['max_overdue'] ?? '3') ?>" min="1" max="10" style="width:100%">
            </div>
            <div class="field"><label>Channel</label>
              <select id="rem-channel" style="width:100%">
                <option value="whatsapp">WhatsApp</option>
                <option value="email">Email</option>
                <option value="both">WhatsApp + Email</option>
              </select>
            </div>
            <div class="field"><label>⏰ Send reminders at (IST)</label>
              <div style="display:flex;gap:8px;align-items:center">
                <select id="rem-send-hour" style="flex:1">
                  <?php for($h=0;$h<24;$h++): ?>
                  <option value="<?=$h?>"><?=sprintf('%02d',$h)?>:00 (<?=$h===0?'12 AM':($h<12?$h.' AM':($h===12?'12 PM':($h-12).' PM'))?>) </option>
                  <?php endfor; ?>
                </select>
                <select id="rem-send-minute" style="width:80px">
                  <option value="0">:00</option>
                  <option value="15">:15</option>
                  <option value="30">:30</option>
                  <option value="45">:45</option>
                </select>
              </div>
              <div id="rem-cron-hint" style="margin-top:6px;font-size:11px;color:var(--muted);background:var(--bg);border-radius:6px;padding:6px 8px;border:1px dashed var(--border2);font-family:var(--mono);line-height:1.5">
                <!-- filled by JS -->
              </div>
            </div>
            <button class="btn btn-success" onclick="saveReminderSettings()" style="width:100%"><i class="fas fa-save"></i> Save Rules</button>
          </div>
        </div>
        <div class="dash-card" style="flex:1;min-width:0">
          <div class="card-header">
            <span class="card-title"><i class="fas fa-list" style="color:var(--amber)"></i> Reminder Queue</span>
            <button class="btn btn-primary" style="font-size:12px" onclick="sendAllReminders()"><i class="fas fa-paper-plane"></i> Send All Now</button>
          </div>
          <div id="rem-queue-stats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px"></div>
          <div id="rem-queue-cards" style="display:flex;flex-direction:column;gap:8px"></div>
        </div>
      </div>

      <div class="dash-card" id="promise-tracker-card" style="margin-bottom:16px">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-handshake" style="color:#6D28D9"></i> Promise to Pay Tracker</span>
          <span id="promise-count-badge" style="background:#EDE9FE;color:#6D28D9;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;display:none">0</span>
        </div>
        <div id="promise-list" style="padding:8px 0">
          <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px">
            <i class="fas fa-handshake" style="font-size:24px;opacity:.2;display:block;margin-bottom:8px"></i>No active promises
          </div>
        </div>
      </div>

      <!-- ── Auto-Reminder Health Check ─────────────────────────── -->
      <div class="dash-card" id="rem-health-card" style="margin-bottom:16px">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-heartbeat" style="color:#E53935"></i> Auto-Reminder Health</span>
          <button class="btn btn-outline" style="font-size:12px" onclick="_buildHealthCheck()"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
        <div id="rem-health-body" style="padding:4px 0">
          <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px">Loading…</div>
        </div>
      </div>

      <div class="table-card">
        <div style="padding:8px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px;overflow-x:auto;min-height:44px;flex-wrap:nowrap">
          <span style="font-weight:700;font-size:14px;white-space:nowrap">Reminder History</span>
          <input id="rem-hist-search" type="text" placeholder="&#x1F50D; Client / Invoice" oninput="window._remHistPage=1;_renderReminderHistory()" style="font-size:12px;padding:4px 8px;border:1px solid var(--border);border-radius:6px;width:160px;flex-shrink:0">
          <select id="rem-hist-type" onchange="window._remHistPage=1;_renderReminderHistory()" style="font-size:12px;padding:4px 6px;border:1px solid var(--border);border-radius:6px">
            <option value="">All Types</option>
            <option value="due_soon">Due Soon</option>
            <option value="due_today">Due Today</option>
            <option value="due_reminder">Reminder</option>
            <option value="overdue">Overdue</option>
            <option value="followup">Follow-up</option>
            <option value="balance_reminder">Balance</option>
            <option value="promise_reminder">Promise</option>
          </select>
          <select id="rem-hist-channel" onchange="window._remHistPage=1;_renderReminderHistory()" style="font-size:12px;padding:4px 6px;border:1px solid var(--border);border-radius:6px">
            <option value="">All Channels</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="email">Email</option>
            <option value="both">Both</option>
          </select>
          <select id="rem-hist-status" onchange="window._remHistPage=1;_renderReminderHistory()" style="font-size:12px;padding:4px 6px;border:1px solid var(--border);border-radius:6px">
            <option value="">All Status</option>
            <option value="sent">Sent</option>
            <option value="failed">Failed</option>
            <option value="skipped">Skipped</option>
            <option value="promise">Promise</option>
          </select>
          <button class="btn btn-outline" style="font-size:12px;margin-left:auto;white-space:nowrap" onclick="clearReminderHistory()"><i class="fas fa-trash"></i> Clear</button>
        </div>
        <table class="data-table"><thead><tr>
          <th>Sent At</th><th>Invoice</th><th>Client</th><th>Type</th><th>Channel</th><th>Status</th><th>Message</th>
        </tr></thead><tbody id="rem-history-tbody"></tbody></table>
        <div id="rem-hist-pagination" style="display:none;align-items:center;justify-content:space-between;padding:10px 16px;border-top:1px solid var(--border);background:var(--bg)">
          <span id="rem-hist-page-info" style="font-size:12px;color:var(--muted)"></span>
          <div id="rem-hist-page-btns" style="display:flex;gap:5px"></div>
        </div>
      </div>
    
    <!--


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/pages/shared-data.js"></script>
<script src="/assets/js/pages/wa-shared.js"></script>
<script src="/assets/js/pages/reminders.js"></script>

<?php
// ================================================================
//  includes/modals/balance_reminder.php
//  Global modal — layout_footer.php already includes this on every
//  page (was referenced there but the file didn't exist yet in the
//  zip, which meant every page was throwing a fatal PHP include
//  error). Backs openBalanceReminderModal() in invoices.js
//  (invoice-render-shared.js additions, Phase 3).
// ================================================================
?>
<div id="balance-reminder-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:460px;box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden">
    <div style="background:linear-gradient(135deg,#D97706,#92400E);padding:18px 20px;display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:10px;color:#fff">
        <i class="fas fa-bell" style="font-size:18px"></i>
        <div>
          <div style="font-weight:800;font-size:15px">Send Balance Reminder</div>
          <div id="br-inv-label" style="font-size:11px;opacity:.8"></div>
        </div>
      </div>
      <button onclick="closeBalanceReminderModal()" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:15px">&#x2715;</button>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">

      <!-- Summary strip -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1px;background:var(--border);border-radius:10px;overflow:hidden">
        <div style="background:#FFF8E1;padding:12px;text-align:center">
          <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#92400E;margin-bottom:3px">Total</div>
          <div id="br-total" style="font-size:15px;font-weight:800;font-family:var(--mono);color:#1A1A2E"></div>
        </div>
        <div style="background:#E8F5E9;padding:12px;text-align:center">
          <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#2E7D32;margin-bottom:3px">Paid</div>
          <div id="br-paid" style="font-size:15px;font-weight:800;font-family:var(--mono);color:#2E7D32"></div>
        </div>
        <div style="background:#FFEBEE;padding:12px;text-align:center">
          <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#C62828;margin-bottom:3px">Balance Due</div>
          <div id="br-remaining" style="font-size:16px;font-weight:800;font-family:var(--mono);color:#C62828"></div>
        </div>
      </div>

      <!-- Channel selector -->
      <div>
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:6px">Send via</label>
        <div style="display:flex;gap:8px">
          <button id="br-ch-wa"    onclick="setBRChannel('whatsapp')" style="flex:1;padding:9px;border-radius:8px;border:2px solid #25D366;background:#25D36615;color:#1a7a3c;font-size:12px;font-weight:700;cursor:pointer">&#128172; WhatsApp</button>
          <button id="br-ch-email" onclick="setBRChannel('email')"    style="flex:1;padding:9px;border-radius:8px;border:2px solid var(--border);background:var(--bg);color:var(--muted);font-size:12px;font-weight:700;cursor:pointer">&#128140; Email</button>
          <button id="br-ch-both"  onclick="setBRChannel('both')"     style="flex:1;padding:9px;border-radius:8px;border:2px solid var(--border);background:var(--bg);color:var(--muted);font-size:12px;font-weight:700;cursor:pointer">&#128172;+&#128140; Both</button>
        </div>
      </div>

      <!-- Message preview -->
      <div>
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:6px">Message preview <span style="font-weight:400;text-transform:none">(editable)</span></label>
        <textarea id="br-message" rows="7" style="width:100%;padding:10px 12px;border:2px solid var(--border);border-radius:8px;font-size:12px;line-height:1.6;font-family:var(--mono);resize:vertical"></textarea>
      </div>

      <div style="display:flex;gap:8px">
        <button onclick="closeBalanceReminderModal()" style="flex:1;padding:11px;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font)">Cancel</button>
        <button onclick="sendBalanceReminder()" style="flex:2;padding:11px;background:linear-gradient(135deg,#D97706,#92400E);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font)"><i class="fas fa-paper-plane"></i> Send Reminder</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Promise-to-Pay Modal ──────────────────────────────────── -->

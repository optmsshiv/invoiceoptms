<div id="promise-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:420px;box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden">
    <div style="background:linear-gradient(135deg,#6D28D9,#4C1D95);padding:18px 20px;display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:10px;color:#fff">
        <i class="fas fa-handshake" style="font-size:18px"></i>
        <div>
          <div style="font-weight:800;font-size:15px">Promise to Pay</div>
          <div id="ptp-inv-label" style="font-size:11px;opacity:.8"></div>
        </div>
      </div>
      <button onclick="closePromiseModal()" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:15px">&#x2715;</button>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
      <div>
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:5px">Client promised to pay by</label>
        <input type="date" id="ptp-date" style="width:100%;padding:10px 12px;border:2px solid var(--border);border-radius:8px;font-size:14px;font-weight:700;color:#6D28D9;font-family:var(--font)">
      </div>
      <div>
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:5px">Amount promised (Rs.)</label>
        <input type="number" id="ptp-amount" placeholder="Leave blank for full remaining amount" style="width:100%;padding:10px 12px;border:2px solid var(--border);border-radius:8px;font-size:14px;font-family:var(--font)">
      </div>
      <div>
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:5px">Remind via</label>
        <select id="ptp-channel" style="width:100%;padding:10px 12px;border:2px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--font)">
          <option value="whatsapp">WhatsApp</option>
          <option value="email">Email</option>
          <option value="both">WhatsApp + Email</option>
        </select>
      </div>
      <div>
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:5px">Note (optional)</label>
        <textarea id="ptp-note" rows="2" placeholder="e.g. Will pay after salary on 1st" style="width:100%;padding:10px 12px;border:2px solid var(--border);border-radius:8px;font-size:13px;resize:none;font-family:var(--font)"></textarea>
      </div>
      <div style="background:#F5F3FF;border-radius:8px;padding:10px 12px;font-size:12px;color:#6D28D9;display:flex;gap:8px;align-items:flex-start">
        <i class="fas fa-info-circle" style="margin-top:2px;flex-shrink:0"></i>
        <span>A reminder will be sent to the client on the promised date. The invoice is suppressed from the queue until then.</span>
      </div>
      <div style="display:flex;gap:8px">
        <button onclick="closePromiseModal()" style="flex:1;padding:11px;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font)">Cancel</button>
        <button onclick="savePromise()" style="flex:2;padding:11px;background:linear-gradient(135deg,#6D28D9,#4C1D95);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font)">Save Promise</button>
      </div>
    </div>
  </div>
</div>

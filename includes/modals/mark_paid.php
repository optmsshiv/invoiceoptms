<div class="modal-overlay" id="modal-paid">
  <div class="modal" style="max-width:500px;max-height:92vh;display:flex;flex-direction:column;">

    <!-- Header -->
    <div class="modal-header" style="padding:16px 20px;flex-shrink:0">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--teal-bg);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-receipt" style="color:var(--teal);font-size:14px"></i>
        </div>
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--text)">Record Payment</div>
          <div id="paid-inv-subtitle" style="font-size:11px;color:var(--muted);font-weight:400;margin-top:1px"></div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal('modal-paid')"><i class="fas fa-times"></i></button>
    </div>

    <!-- Scrollable body -->
    <div class="modal-body" style="overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:12px">

      <!-- Invoice summary strip -->
      <div id="paid-inv-summary" style="background:linear-gradient(135deg,var(--teal),#00695C);border-radius:10px;padding:12px 16px;color:#fff">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div>
            <div style="font-size:10px;opacity:.7;text-transform:uppercase;letter-spacing:.8px;margin-bottom:2px">Invoice</div>
            <div style="font-size:15px;font-weight:800;font-family:var(--mono)" id="paid-inv-num"></div>
            <div style="font-size:12px;opacity:.85;margin-top:2px" id="paid-inv-client"></div>
          </div>
          <div style="text-align:right">
            <div style="font-size:10px;opacity:.7;text-transform:uppercase;letter-spacing:.8px;margin-bottom:2px">Grand Total</div>
            <div style="font-size:18px;font-weight:800;font-family:var(--mono)" id="paid-inv-total"></div>
          </div>
        </div>
        <!-- Already paid + remaining chips row -->
        <div id="paid-inv-remaining-row" style="display:none;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.25);display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <!-- Already Paid — orange chip -->
          <div style="backdrop-filter: blur(6px);display:inline-flex;align-items:center;gap:5px;background:rgba(255,152,0,.28);border:1px solid rgba(255,152,0,.55);border-radius:20px;padding:3px 10px 3px 7px">
            <span style="width:7px;height:7px;border-radius:50%;background:#FFB300;flex-shrink:0;box-shadow:0 0 0 2px rgba(255,179,0,.35)"></span>
            <span style="font-size:12px;font-weight:600;color:#FFE082;white-space:nowrap">Already Paid&nbsp;</span>
            <strong id="paid-inv-already" style="font-family:var(--mono);font-size:12px;color:#fff"></strong>
          </div>
          <!-- Remaining — matte red chip -->
          <div style="margin-left:auto;backdrop-filter: blur(6px);display:inline-flex;align-items:center;gap:5px;background:rgba(229,57,53,.28);border:1px solid rgba(229,57,53,.5);border-radius:20px;padding:3px 10px 3px 7px">
            <span style="width:7px;height:7px;border-radius:50%;background:#EF5350;flex-shrink:0;box-shadow:0 0 0 2px rgba(239,83,80,.35)"></span>
            <span style="font-size:12px;font-weight:600;color:#FFCDD2;white-space:nowrap">Remaining Due&nbsp;</span>
            <strong id="paid-inv-remaining" style="font-family:var(--mono);font-size:12px;color:#fff"></strong>
          </div>
        </div>
      </div>

      <!-- Date + Method (2-col) — time moved to view-only display beside Settlement Discount -->
      <input type="hidden" id="paid-time">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="field">
          <label>Payment Date</label>
          <input type="date" id="paid-date">
        </div>
        <div class="field">
          <label>Method</label>
          <select id="paid-method" onchange="toggleSplitPayment()">
            <option>UPI (GPay/PhonePe/Paytm)</option>
            <option>Bank Transfer (NEFT/RTGS)</option>
            <option>Cash</option>
            <option>Cheque</option>
            <option>Credit Card</option>
            <option value="Split">⚡ Split Payment</option>
          </select>
        </div>
      </div>

      <!-- Amount + Txn ID (2-col) -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="field" id="paid-amt-field">
          <label>Amount Received (₹) <span id="paid-amt-label-note" style="font-size:10px;font-weight:400;color:var(--muted)"></span></label>
          <input type="number" id="paid-amt" placeholder="0.00" oninput="onPaidAmtInput()">
        </div>
        <div class="field">
          <label>Transaction ID / UTR</label>
          <input id="paid-txn" placeholder="Ref / UTR Number">
        </div>
      </div>

      <!-- Settlement Discount -->
      <div class="field" id="paid-settle-disc-row">
        <label style="display:flex;align-items:center;justify-content:space-between;gap:6px">
          <span style="display:flex;align-items:center;gap:6px">
            Settlement Discount
            <span style="font-size:10px;font-weight:400;color:var(--muted);background:var(--amber-bg);border:1px solid var(--amber);border-radius:4px;padding:1px 6px">optional</span>
          </span>
          <span id="paid-time-display" title="Payment time (view only)" style="font-size:11px;font-weight:400;color:var(--muted);font-family:var(--mono);text-transform:none;letter-spacing:0"><i class="fas fa-clock" style="margin-right:4px;opacity:.7"></i></span>
        </label>
        <div style="display:flex;gap:6px;align-items:center">
          <select id="paid-settle-disc-type" style="width:90px;flex-shrink:0" onchange="onPaidSettleDiscInput()">
            <option value="pct">%</option>
            <option value="fixed">₹ Fixed</option>
          </select>
          <input type="number" id="paid-settle-disc" value="0" min="0" step="0.01" style="flex:1" oninput="onPaidSettleDiscInput()" placeholder="0">
          <span id="paid-settle-disc-display" style="font-size:12px;font-weight:700;color:#E65100;min-width:70px;text-align:right;display:none"></span>
        </div>
        <div id="paid-settle-disc-info" style="display:none;font-size:11px;color:#E65100;margin-top:4px;background:#FFF3E0;border-radius:6px;padding:5px 8px;border:1px solid #FFCC80"></div>
      </div>

      <!-- Notes -->
      <div class="field">
        <label>Notes <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
        <input id="paid-notes" placeholder="e.g. First instalment received">
      </div>

      <!-- Partial payment box — no overflow:hidden, with % column -->
      <div id="paid-remaining-box" style="display:none;border-radius:10px;border:1.5px solid #FFD54F">
        <div style="background:linear-gradient(135deg,#FF8F00,#FFA000);border-radius:8px 8px 0 0;padding:9px 14px;display:flex;align-items:center;gap:8px">
          <i class="fas fa-exclamation-triangle" style="color:#fff;font-size:12px"></i>
          <span style="color:#fff;font-weight:700;font-size:12px">Partial Payment Detected</span>
        </div>
        <div style="background:#FFFDE7;border-radius:0 0 8px 8px;padding:12px 14px">
          <!-- 4-col stats: Total | Received | Remaining | Paid % -->
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-bottom:10px">
            <div style="background:#fff;border-radius:7px;padding:8px 10px;border:1px solid #FFE082;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.7px;margin-bottom:3px">Total</div>
              <div id="paid-rem-total" style="font-size:13px;font-weight:800;color:#333;font-family:var(--mono)">₹0.00</div>
            </div>
            <div style="background:#fff;border-radius:7px;padding:8px 10px;border:1px solid #A5D6A7;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.7px;margin-bottom:3px">Received</div>
              <div id="paid-rem-received" style="font-size:13px;font-weight:800;color:#2E7D32;font-family:var(--mono)">₹0.00</div>
            </div>
            <div style="background:#fff;border-radius:7px;padding:8px 10px;border:1px solid #FFCDD2;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.7px;margin-bottom:3px">Remaining</div>
              <div id="paid-rem-due" style="font-size:13px;font-weight:800;color:#C62828;font-family:var(--mono)">₹0.00</div>
            </div>
            <div style="background:#fff;border-radius:7px;padding:8px 10px;border:1px solid #CE93D8;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.7px;margin-bottom:3px">Paid %</div>
              <div id="paid-rem-pct" style="font-size:13px;font-weight:800;color:#7B1FA2;font-family:var(--mono)">0%</div>
            </div>
          </div>
          <div style="height:5px;background:#FFE082;border-radius:3px;margin-bottom:10px;overflow:hidden">
            <div id="paid-rem-bar" style="height:100%;background:linear-gradient(90deg,#43A047,#66BB6A);border-radius:3px;width:0%;transition:width .4s"></div>
          </div>
          <!-- Discount breakdown footer — shown only when settlement discount > 0 -->
          <div id="paid-rem-breakdown" style="display:none;font-size:11px;color:#5D4037;background:#FFF8E1;border:1px dashed #FFD54F;border-radius:7px;padding:7px 11px;margin-bottom:8px;font-family:var(--mono);line-height:1.6">
            <span style="font-weight:700;color:#E65100">Total Covered:</span>
            <span id="paid-rem-breakdown-text"></span>
          </div>
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;background:#fff;border-radius:8px;padding:10px 12px;border:1.5px solid #FFD54F">
            <input type="checkbox" id="paid-collect-remaining" style="accent-color:#E65100;width:15px;height:15px;flex-shrink:0;margin-top:1px">
            <div>
              <div style="font-size:12px;font-weight:700;color:#E65100">Record as partial payment</div>
              <div style="font-size:11px;color:#795548;margin-top:2px">Invoice stays active — collect remaining amount later. If unchecked, invoice will be marked Paid.</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Split Payment Panel -->
      <div id="split-payment-panel" style="display:none;background:#F8F9FA;border-radius:10px;padding:12px;border:1.5px solid #E65100">
        <div style="font-size:11px;font-weight:700;color:#E65100;margin-bottom:10px;display:flex;align-items:center;gap:6px">
          <i class="fas fa-bolt"></i> Split Payment — Amount per method
        </div>
        <div style="display:flex;flex-direction:column;gap:7px" id="split-rows">
          <div class="split-row" style="display:flex;gap:7px;align-items:center">
            <select class="split-method" style="flex:1;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px;min-width:0" onchange="renderSplitBreakdown()">
              <option>UPI (GPay/PhonePe/Paytm)</option>
              <option>Bank Transfer (NEFT/RTGS)</option>
              <option>Cash</option><option>Cheque</option><option>Credit Card</option>
            </select>
            <input type="number" class="split-amt" placeholder="0.00" value="" style="width:90px;flex-shrink:0;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px;font-family:var(--mono)" oninput="updateSplitTotal()">
            <button onclick="removeSplitRow(this)" style="width:28px;height:28px;flex-shrink:0;background:#FFEBEE;color:#C62828;border:none;border-radius:7px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center">✕</button>
          </div>
          <div class="split-row" style="display:flex;gap:7px;align-items:center">
            <select class="split-method" style="flex:1;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px;min-width:0" onchange="renderSplitBreakdown()">
              <option>Cash</option>
              <option>UPI (GPay/PhonePe/Paytm)</option>
              <option>Bank Transfer (NEFT/RTGS)</option>
              <option>Cheque</option><option>Credit Card</option>
            </select>
            <input type="number" class="split-amt" placeholder="0.00" value="" style="width:90px;flex-shrink:0;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px;font-family:var(--mono)" oninput="updateSplitTotal()">
            <button onclick="removeSplitRow(this)" style="width:28px;height:28px;flex-shrink:0;background:#FFEBEE;color:#C62828;border:none;border-radius:7px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center">✕</button>
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
          <button onclick="addSplitRow()" style="padding:5px 12px;background:#E8F5E9;color:#2E7D32;border:1.5px solid #A5D6A7;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600">+ Add Method</button>
          <div style="font-size:12px;color:var(--muted)">Total: <strong id="split-total" style="color:#E65100;font-family:var(--mono)">₹0.00</strong></div>
        </div>
        <div id="split-breakdown-bar" style="display:none;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px;padding:7px 10px;background:#fff;border-radius:7px;border:1px solid #e0e0e0;font-size:12px"></div>
        <div id="split-mismatch-warn" style="display:none;margin-top:8px;font-size:11px;color:#C62828;background:#FFEBEE;border-radius:6px;padding:6px 10px;font-weight:600"></div>
      </div>

    </div><!-- end modal-body -->

    <!-- Footer -->
    <div class="modal-footer" style="padding:14px 20px;flex-shrink:0">
      <button id="btn-confirm-paid" class="btn btn-success" onclick="confirmPaid()" style="flex:1"><i class="fas fa-check"></i> Confirm Payment</button>
      <button class="btn btn-outline" onclick="closeModal('modal-paid')" style="padding:9px 20px">Cancel</button>
    </div>

  </div>
</div>

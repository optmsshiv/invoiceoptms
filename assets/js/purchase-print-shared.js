// ================================================================
//  assets/js/purchase-print-shared.js
//  Requires: common.js, shared-data.js (loaded before this file —
//  needs pneCompanyInfo(), numToWordsINR(), pnePaymentStamp() from
//  common.js).
//
//  Shared between pages/purchases/purchase-new.php (Save & Print)
//  and pages/purchases/purchases.php (list page's Print action).
//  Two templates: Local Purchase Voucher (Farmer/GST-exempt) and
//  Tax Invoice Purchase (regular GST), chosen automatically by
//  printPurchaseEntry() based on the purchase's gst_applicable flag.
// ================================================================

async function printPurchaseEntry(id) {
  try {
    const r = await api('api/purchases.php?id=' + id);
    const p = r.data;
    const isFarmerVoucher = !parseInt(p.gst_applicable) || p.supplier_type === 'Farmer';
    if (isFarmerVoucher) printLocalPurchaseVoucher(p); else printTaxInvoicePurchase(p);
  } catch(e) { toast('❌ Could not open print view: ' + e.message, 'error'); }
}

function pneStatutoryLine(co) {
  const parts = [];
  if (co.gst) parts.push('GSTIN: ' + escHtml(co.gst));
  if (co.pan) parts.push('PAN: ' + escHtml(co.pan));
  if (co.fssai) parts.push('FSSAI: ' + escHtml(co.fssai));
  return parts.join(' &nbsp;|&nbsp; ');
}

// ── Template 1: Local Purchase Voucher (Farmer / GST-exempt purchases) ──
function printLocalPurchaseVoucher(p) {
  const co = pneCompanyInfo();
  const items = p.items || [];
  const rows = items.map(it => `
    <tr>
      <td>${escHtml(it.description||'')}</td><td>${escHtml(it.variety_grade||'—')}</td><td>${escHtml(it.quality_grade||'—')}</td>
      <td class="r">${it.moisture_pct ? parseFloat(it.moisture_pct).toFixed(1)+'%' : '—'}</td>
      <td class="r">${parseFloat(it.gross_weight).toFixed(2)}</td><td class="r">${parseFloat(it.tare_weight).toFixed(2)}</td>
      <td class="r">${parseFloat(it.qty).toFixed(2)}</td><td class="r">${(STATE.settings.showDhaltaPct ?? '1') !== '0' ? parseFloat(it.dhalta_pct||0).toFixed(1)+'%' : parseFloat(it.dhalta_kg||0).toFixed(2)}</td>
      <td class="r">${parseFloat(it.billable_weight).toFixed(2)}</td><td class="r">${fmt_money(it.rate)}</td><td class="r">${fmt_money(it.amount)}</td>
    </tr>`).join('');
  const gGross = items.reduce((s,i)=>s+parseFloat(i.gross_weight||0),0);
  const gTare  = items.reduce((s,i)=>s+parseFloat(i.tare_weight||0),0);
  const gNet   = items.reduce((s,i)=>s+parseFloat(i.qty||0),0);
  const gBill  = items.reduce((s,i)=>s+parseFloat(i.billable_weight||0),0);
  const gAmt   = items.reduce((s,i)=>s+parseFloat(i.amount||0),0);
  const addCharges = (parseFloat(p.transport_charge)||0)+(parseFloat(p.loading_charge)||0)+(parseFloat(p.packing_charge)||0)+(parseFloat(p.other_charges)||0);
  const deductions = Array.isArray(p.deductions) ? p.deductions : [];
  const deductionTotal = deductions.reduce((sum,d) => sum + (parseFloat(d.amount)||0), 0);

  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>${escHtml(p.purchase_no)}</title><style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1a2b3c; padding: 26px 34px; font-size: 12.5px; position: relative; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d3b2e; padding-bottom: 14px; margin-bottom: 16px; }
    .co-name { font-size: 19px; font-weight: 800; color: #0d3b2e; }
    .co-sub { font-size: 10.5px; color: #6b7c93; letter-spacing: .5px; }
    .co-meta { font-size: 10.5px; color: #445; margin-top: 6px; line-height: 1.6; }
    .badge-voucher { border: 1.5px solid #0d3b2e; color: #0d3b2e; font-weight: 700; font-size: 12px; padding: 8px 16px; border-radius: 20px; text-align: center; }
    .voucher-meta { text-align: right; font-size: 11.5px; color: #445; margin-top: 8px; line-height: 1.7; }
    .row2 { display: flex; gap: 16px; margin-bottom: 16px; }
    .box { flex: 1; border: 1px solid #dde3ea; border-radius: 8px; padding: 14px 16px; }
    .box h3 { font-size: 12px; color: #0d3b2e; margin: 0 0 10px; display: flex; align-items: center; gap: 6px; }
    .box .kv { font-size: 11px; color: #667; margin-bottom: 8px; }
    .box .kv b { display: block; font-size: 12.5px; color: #223; font-weight: 700; }
    .pills { display: flex; gap: 6px; }
    .pill { font-size: 9.5px; font-weight: 700; padding: 3px 9px; border-radius: 10px; }
    .pill.gray { background: #eef0f3; color: #556; } .pill.green { background: #e3f6ea; color: #0d7a3f; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
    table.items th { background: #eef0f3; color: #0d3b2e; padding: 8px 7px; font-size: 10px; text-transform: uppercase; text-align: left; border-bottom: 2px solid #0d3b2e; }
    table.items td { padding: 7px; border-bottom: 1px solid #eef0f3; }
    table.items td.r, table.items th.r { text-align: right; }
    tfoot td { border-top: 2px solid #0d3b2e; border-bottom: 2px solid #0d3b2e; color: #0d3b2e; font-weight: 700; padding: 9px 7px; background: #fff; }
    .row3 { display: flex; gap: 16px; margin-bottom: 16px; }
    .ded-row { display: flex; justify-content: space-between; font-size: 11.5px; padding: 5px 0; color: #445; }
    .ded-total { border-top: 1px solid #dde3ea; margin-top: 6px; padding-top: 8px; font-weight: 700; color: #c0392b; display: flex; justify-content: space-between; }
    .pay-row { display: flex; justify-content: space-between; font-size: 11.5px; padding: 5px 0; color: #445; }
    .pay-net { border-top: 1px solid #dde3ea; margin-top: 6px; padding-top: 8px; font-weight: 800; font-size: 15px; color: #0d3b2e; display: flex; justify-content: space-between; }
    .paymode { display: flex; gap: 6px; margin-top: 10px; }
    .paymode span { flex: 1; text-align: center; padding: 6px; border-radius: 6px; font-size: 10.5px; font-weight: 700; background: #eef0f3; color: #889; }
    .paymode span.active { background: #0d3b2e; color: #fff; }
    .remark { font-style: italic; color: #556; font-size: 11px; line-height: 1.6; }
    .attach div { font-size: 11px; color: #445; margin-bottom: 6px; }
    .sig-row { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 10px; }
    .sig { width: 30%; border-top: 1px solid #99a; text-align: center; font-size: 10px; color: #667; padding-top: 6px; text-transform: uppercase; letter-spacing: .5px; }
    .footer { margin-top: 30px; border-top: 1px solid #eef0f3; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9.5px; color: #99a; }
  </style></head><body>
    <div class="head">
      <div style="display:flex;gap:12px;align-items:flex-start">
        ${co.logo ? `<img src="${co.logo}" alt="Logo" style="width:92px;height:92px;object-fit:contain;border-radius:6px">` : ''}
        <div>
          <div class="co-name">${escHtml(co.name)}</div>
          <div class="co-meta">
            ${pneStatutoryLine(co)}${pneStatutoryLine(co)?'<br>':''}
            ${co.iec?`IEC No: ${escHtml(co.iec)}`:''} ${co.phone?' &nbsp; Mobile: '+escHtml(co.phone):''}${co.email?' &nbsp; Email: '+escHtml(co.email):''}<br>
            ${co.address?`Address: ${escHtml(co.address)}`:''}
          </div>
        </div>
      </div>
      <div>
        <div class="badge-voucher">LOCAL PURCHASE<br>VOUCHER</div>
        <div class="voucher-meta">Voucher No: ${escHtml(p.purchase_no)}<br>Date: ${fmt_date_disp(p.purchase_date)}<br>Warehouse: ${escHtml(p.warehouse||'')}</div>
      </div>
    </div>
    ${pnePaymentStamp(p.payment_status||p.status)}

    <div class="row2">
      <div class="box">
        <h3>👤 FARMER INFORMATION <span class="pills"><span class="pill gray">SUPPLIER: ${escHtml((p.supplier_type||'FARMER').toUpperCase())}</span><span class="pill green">GST: EXEMPT</span></span></h3>
        <div class="kv">Farmer Name<b>${escHtml(p.supplier_name||'')}</b></div>
        <div class="kv">Location<b>${escHtml(p.district||p.state||'—')}</b></div>
        <div class="kv">Mobile Number<b>${escHtml(p.supplier_phone||'—')}</b></div>
      </div>
      <div class="box">
        <h3>🚚 LOGISTICS</h3>
        <div class="kv">Vehicle No<b>${escHtml(p.vehicle_no||'—')}</b></div>
        <div class="kv">Driver Name<b>${escHtml(p.driver_name||'—')}</b></div>
        <div class="kv">Mode<b>${escHtml(p.transport_mode||'—')}</b></div>
      </div>
    </div>

    <table class="items">
      <thead><tr><th>Product</th><th>Variety</th><th>Grade</th><th class="r">Moist%</th><th class="r">Gross (Kg)</th><th class="r">Tare (Kg)</th><th class="r">Net (Kg)</th><th class="r">${(STATE.settings.showDhaltaPct ?? '1') !== '0' ? 'Dhalta%' : 'Dhalta (Kg)'}</th><th class="r">Billable (Kg)</th><th class="r">Rate/Kg</th><th class="r">Amount</th></tr></thead>
      <tbody>${rows}</tbody>
      <tfoot><tr><td colspan="4">GRAND TOTALS:</td><td class="r">${gGross.toFixed(2)}</td><td class="r">${gTare.toFixed(2)}</td><td class="r">${gNet.toFixed(2)}</td><td class="r">—</td><td class="r">${gBill.toFixed(2)}</td><td class="r">—</td><td class="r">${fmt_money(gAmt)}</td></tr></tfoot>
    </table>

    <div class="row3">
      <div class="box">
        <h3>ADDITIONAL CHARGES</h3>
        <div class="ded-row"><span>Loading &amp; Unloading</span><span>${fmt_money(p.loading_charge)}</span></div>
        <div class="ded-row"><span>Transport Allowance</span><span>${fmt_money(p.transport_charge)}</span></div>
        <div class="ded-row"><span>Packing Materials</span><span>${fmt_money(p.packing_charge)}</span></div>
        <div class="ded-row"><span>Other / Mandi Tax</span><span>${fmt_money(p.other_charges)}</span></div>
        <div class="ded-total" style="color:#0d7a3f"><span>Total Charges</span><span>+ ${fmt_money(addCharges)}</span></div>
      </div>
      <div class="box">
        <h3>PAYMENT SETTLEMENT</h3>
        <div class="pay-row"><span>Subtotal Amount</span><span>${fmt_money(p.subtotal)}</span></div>
        <div class="pay-row" style="color:#0d7a3f"><span>Additional Charges</span><span>+ ${fmt_money(addCharges)}</span></div>
        ${(parseFloat(p.discount_amount)||0) > 0 ? `<div class="pay-row" style="color:#c0392b"><span>Discount${p.discount_remarks?` (${escHtml(p.discount_remarks)})`:''}</span><span>- ${fmt_money(p.discount_amount)}</span></div>` : ''}
        ${deductionTotal > 0 ? `<div class="pay-row" style="color:#c0392b"><span>Deductions</span><span>- ${fmt_money(deductionTotal)}</span></div>` : ''}
        ${(parseFloat(p.trade_discount_amount)||0) > 0 ? `<div class="pay-row" style="color:#c0392b"><span>Trade Discount</span><span>- ${fmt_money(p.trade_discount_amount)}</span></div>` : ''}
        ${(parseFloat(p.cash_discount_amount)||0) > 0 ? `<div class="pay-row" style="color:#c0392b"><span>Cash Discount</span><span>- ${fmt_money(p.cash_discount_amount)}</span></div>` : ''}
        <div class="pay-net"><span>Net Payable</span><span>${fmt_money(p.total)}</span></div>
        <div class="paymode">
          <span class="${p.payment_mode==='Cash'?'active':''}">Cash</span>
          <span class="${(p.payment_mode||'').includes('Bank')?'active':''}">Bank Transfer</span>
          <span class="${(p.payment_mode||'').includes('UPI')?'active':''}">UPI</span>
        </div>
      </div>
    </div>
    ${deductions.length ? `
    <div class="box" style="margin-top:12px">
      <h3>DEDUCTION DETAILS</h3>
      ${deductions.map(d => `<div class="ded-row"><span>${escHtml(d.type||'Deduction')}${d.description?` — ${escHtml(d.description)}`:''}</span><span>${fmt_money(d.amount)}</span></div>`).join('')}
    </div>` : ''}

    ${p.remarks ? `<div class="box" style="margin-bottom:16px"><h3>OBSERVATIONS &amp; REMARKS</h3><div class="remark">${escHtml(p.remarks)}</div></div>` : ''}

    <div class="sig-row">
      <div class="sig">Farmer Signature / Thumb</div>
      <div class="sig">Receiving Officer</div>
      <div class="sig" style="border-top-color:#0d3b2e;color:#0d3b2e;font-weight:700">Authorized Signatory</div>
    </div>
    <div class="footer">
      <span>${escHtml(p.purchase_no)} — This is a system generated document</span>
      <span>Printed on: ${fmt_date_disp(new Date())}</span>
    </div>
    <script>window.print();<\/script>
  </body></html>`);
  win.document.close();
}

// ── Template 2: Tax Invoice — Purchase Entry (regular / GST-applicable purchases) ──
function printTaxInvoicePurchase(p) {
  const co = pneCompanyInfo();
  const items = p.items || [];
  const rows = items.map(it => `
    <tr>
      <td><strong>${escHtml(it.description||'')}</strong><br><span class="muted">${escHtml(it.variety_grade||'')}</span></td>
      <td>${escHtml(it.hsn||'—')}</td>
      <td class="r">${parseFloat(it.qty).toFixed(2)} Kg</td>
      <td class="r">${fmt_money(it.rate)}</td>
      <td class="r">${fmt_money(it.amount)}</td>
      <td class="r">${parseFloat(it.gst_pct||0).toFixed(0)}%</td>
      <td class="r">${fmt_money((it.amount||0) * (it.gst_pct||0) / 100)}</td>
      <td class="r"><strong>${fmt_money((it.amount||0) * (1 + (it.gst_pct||0)/100))}</strong></td>
    </tr>`).join('');
  const isInterstate = p.supply_type === 'Inter-State';
  const deductions = Array.isArray(p.deductions) ? p.deductions : [];
  const deductionTotal = deductions.reduce((sum,d) => sum + (parseFloat(d.amount)||0), 0);

  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>${escHtml(p.purchase_no)}</title><style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1a2b3c; padding: 26px 34px; font-size: 12.5px; position: relative; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d3b2e; padding-bottom: 14px; margin-bottom: 16px; }
    .co-name { font-size: 19px; font-weight: 800; color: #0d3b2e; }
    .co-sub { font-size: 10.5px; color: #6b7c93; letter-spacing: .5px; }
    .co-meta { font-size: 10.5px; color: #445; margin-top: 6px; line-height: 1.6; }
    .badge-inv { border: 1.5px solid #0d3b2e; color: #0d3b2e; font-weight: 700; font-size: 12px; padding: 8px 16px; border-radius: 8px; text-align: center; }
    .badge-inv small { display: block; font-size: 9px; font-weight: 600; color: #6b7c93; }
    .inv-meta { text-align: right; font-size: 11px; color: #445; margin-top: 8px; line-height: 1.7; }
    .row2 { display: flex; gap: 16px; margin-bottom: 16px; }
    .box { flex: 1; border: 1px solid #dde3ea; border-radius: 8px; padding: 14px 16px; }
    .box h3 { font-size: 11.5px; color: #0d3b2e; margin: 0 0 10px; }
    .box .kv { font-size: 11px; color: #667; margin-bottom: 7px; }
    .box .kv b { display: block; font-size: 12.5px; color: #223; font-weight: 700; }
    .wt-bar { background: #0d3b2e; color: #fff; border-radius: 8px; padding: 12px 18px; display: flex; justify-content: space-between; margin-bottom: 16px; }
    .wt-bar div { text-align: center; flex: 1; }
    .wt-bar span { display: block; font-size: 9.5px; opacity: .8; text-transform: uppercase; }
    .wt-bar b { font-size: 14px; }
    .wt-bar .billable { background: rgba(255,255,255,.15); border-radius: 6px; padding: 4px 8px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
    table.items th { background: #f3f5f7; color: #445; padding: 8px 7px; font-size: 10px; text-transform: uppercase; text-align: left; border-bottom: 2px solid #dde3ea; }
    table.items td { padding: 8px 7px; border-bottom: 1px solid #eef0f3; vertical-align: top; }
    table.items td.r, table.items th.r { text-align: right; }
    .muted { color: #99a; font-size: 10px; }
    .row3 { display: flex; gap: 16px; margin-bottom: 16px; }
    .tax-row { display: flex; justify-content: space-between; font-size: 11px; padding: 4px 0; color: #445; }
    .sum-row { display: flex; justify-content: space-between; font-size: 12px; padding: 5px 0; color: #445; }
    .grand { border: 2px solid #0d3b2e; color: #0d3b2e; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-top: 8px; background: #fff; }
    .grand span { font-size: 11px; text-transform: uppercase; font-weight: 700; } .grand b { font-size: 20px; color: #0d3b2e; }
    .words { font-style: italic; color: #556; font-size: 11px; margin-top: 10px; }
    .sig-row { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 10px; }
    .sig { width: 30%; border-top: 1px solid #99a; text-align: center; font-size: 10px; color: #667; padding-top: 6px; text-transform: uppercase; letter-spacing: .5px; }
    .footer { margin-top: 30px; border-top: 1px solid #eef0f3; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9.5px; color: #99a; }
    .kanta-box { border: 2px solid #0d3b2e; border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; background: #f5faf7; }
    .kanta-title { font-size: 11px; font-weight: 800; color: #0d3b2e; letter-spacing: 1px; margin-bottom: 12px; }
    .kanta-grid { display: grid; grid-template-columns: repeat(5,1fr); gap: 10px; }
    .kanta-cell { text-align: center; padding: 10px 6px; background: #fff; border-radius: 7px; border: 1px solid #d0e8dc; }
    .kanta-dhalta { border-color: #c0392b !important; background: #fff9f9 !important; }
    .kanta-billable { border-color: #0d7a3f !important; background: #f0faf5 !important; }
    .kanta-label { font-size: 9px; text-transform: uppercase; letter-spacing: .7px; color: #778; margin-bottom: 5px; font-weight: 700; }
    .kanta-val { font-size: 18px; font-weight: 800; color: #0d3b2e; line-height: 1.1; }
    .kanta-dhalta .kanta-val { color: #c0392b; }
    .kanta-billable .kanta-val { color: #0d7a3f; }
    .kanta-unit { font-size: 11px; font-weight: 400; }
    .kanta-pct { font-size: 9.5px; color: #889; margin-top: 3px; }
    .kanta-meta { margin-top: 10px; padding-top: 8px; border-top: 1px dashed #c8ddd5; font-size: 10px; color: #556; }
  </style></head><body>
    <div class="head">
      <div style="display:flex;gap:12px;align-items:flex-start">
        ${co.logo ? `<img src="${co.logo}" alt="Logo" style="width:72px;height:72px;object-fit:contain;border-radius:6px">` : ''}
        <div>
          <div class="co-name">${escHtml(co.name)}</div>
          <div class="co-meta">
            ${co.address?escHtml(co.address)+'<br>':''}
            ${pneStatutoryLine(co)?`<strong>${pneStatutoryLine(co)}</strong><br>`:''}
            ${co.iec?`IEC: ${escHtml(co.iec)}<br>`:''}${co.phone?'Mobile: '+escHtml(co.phone):''}${co.email?(co.phone?' &nbsp;|&nbsp; ':'')+'Email: '+escHtml(co.email):''}
          </div>
        </div>
      </div>
      <div>
        <div class="badge-inv">TAX INVOICE<small>PURCHASE ENTRY</small></div>
        <div class="inv-meta">Invoice No: ${escHtml(p.purchase_no)}<br>Date: ${fmt_date_disp(p.purchase_date)}<br>${p.reference_po_no?`PO Reference: ${escHtml(p.reference_po_no)}`:''}</div>
      </div>
    </div>
    ${pnePaymentStamp(p.payment_status||p.status)}

    <div class="row2">
      <div class="box">
        <h3>SUPPLIER DETAILS</h3>
        <div class="kv"><b>${escHtml(p.supplier_name||'')}</b></div>
        <div class="kv">GSTIN<b>${escHtml(p.supplier_gstin||'—')}</b></div>
        <div class="kv">Contact<b>${escHtml(p.supplier_phone||'—')}</b></div>
      </div>
      <div class="box">
        <h3>SUPPLIER LEDGER SUMMARY</h3>
        <div class="kv">Outstanding Balance<b style="color:#c0392b">${fmt_money((p.total||0)-(p.amount_paid||0))}</b></div>
        <div class="kv">Dispatch / Warehouse<b>${escHtml(p.warehouse||'—')}</b></div>
      </div>
    </div>

    <div class="wt-bar">
      <div><span>Gross Weight</span><b>${parseFloat(p.kanta_gross_weight||0).toFixed(2)} Kg</b></div>
      <div><span>Tare Weight</span><b>${parseFloat(p.kanta_tare_weight||0).toFixed(2)} Kg</b></div>
      <div><span>Net Weight</span><b>${(parseFloat(p.kanta_gross_weight||0)-parseFloat(p.kanta_tare_weight||0)).toFixed(2)} Kg</b></div>
      <div><span>Dhalta</span><b>${parseFloat(p.header_dhalta_kg||0).toFixed(2)} Kg</b></div>
      <div class="billable"><span>Billable Weight</span><b>${parseFloat(p.header_billable_weight||0).toFixed(2)} Kg</b></div>
    </div>

    <table class="items">
      <thead><tr><th>Product &amp; Variety</th><th>HSN</th><th class="r">Weight</th><th class="r">Rate</th><th class="r">Value</th><th class="r">GST %</th><th class="r">Tax Amt</th><th class="r">Total</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>

    <div class="row3">
      <div class="box">
        <h3>TAX SUMMARY</h3>
        <div class="tax-row"><span>Taxable Value</span><span>${fmt_money(p.subtotal)}</span></div>
        ${isInterstate
          ? `<div class="tax-row"><span>IGST</span><span>${fmt_money(p.gst_amount)}</span></div>`
          : `<div class="tax-row"><span>CGST (${(p.gst_pct/2).toFixed(1)}%)</span><span>${fmt_money(p.gst_amount/2)}</span></div>
             <div class="tax-row"><span>SGST (${(p.gst_pct/2).toFixed(1)}%)</span><span>${fmt_money(p.gst_amount/2)}</span></div>`}
      </div>
      <div class="box">
        <div class="sum-row"><span>Sub-Total Amount</span><span>${fmt_money(p.subtotal)}</span></div>
        ${deductionTotal > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Deductions</span><span>- ${fmt_money(deductionTotal)}</span></div>` : ''}
        ${(parseFloat(p.discount_amount)||0) > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Less: Discount${p.discount_remarks?` (${escHtml(p.discount_remarks)})`:''}</span><span>- ${fmt_money(p.discount_amount)}</span></div>` : ''}
        ${(parseFloat(p.trade_discount_amount)||0) > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Trade Discount (${parseFloat(p.trade_discount_pct||0).toFixed(1)}%)</span><span>- ${fmt_money(p.trade_discount_amount)}</span></div>` : ''}
        ${(parseFloat(p.cash_discount_amount)||0) > 0 ? `<div class="sum-row" style="color:#c0392b"><span>Cash Discount (${parseFloat(p.cash_discount_pct||0).toFixed(1)}%)</span><span>- ${fmt_money(p.cash_discount_amount)}</span></div>` : ''}
        <div class="sum-row"><span>Total GST</span><span>${fmt_money(p.gst_amount)}</span></div>
        <div class="sum-row"><span>Round-off</span><span>${fmt_money(0)}</span></div>
        <div class="grand"><span>GRAND TOTAL</span><b>${fmt_money(p.total)}</b></div>
      </div>
    </div>
    <div class="words">Amount in Words: <strong>${numToWordsINR(p.total)}</strong></div>
    ${deductions.length ? `
    <div class="box" style="margin-top:12px">
      <h3>DEDUCTION DETAILS</h3>
      ${deductions.map(d => `<div class="tax-row"><span>${escHtml(d.type||'Deduction')}${d.description?` — ${escHtml(d.description)}`:''}</span><span>${fmt_money(d.amount)}</span></div>`).join('')}
    </div>` : ''}

    <div class="sig-row">
      <div class="sig">Supplier Signature</div>
      <div class="sig">Seal &amp; Signature</div>
      <div class="sig" style="border-top-color:#0d3b2e;color:#0d3b2e;font-weight:700">Authorized Signatory</div>
    </div>
    <div class="footer">
      <span>${escHtml(p.purchase_no)} — This is a system generated document</span>
      <span>Printed on: ${fmt_date_disp(new Date())}</span>
    </div>
    <script>window.print();<\/script>
  </body></html>`);
  win.document.close();
}

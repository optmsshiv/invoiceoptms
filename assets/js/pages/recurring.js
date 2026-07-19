// ================================================================
//  assets/js/recurring.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  MPA CHANGES from the SPA version:
//  1. Dropped the "hook into showPage" block entirely — it existed
//     only to reload data when the SPA router switched to this page
//     or the whatsapp page. Real page loads make that redundant;
//     this file's own DOMContentLoaded listener handles it.
//  2. runRecurringCheck()'s auto-WA-send branch (when a schedule's
//     wa.auto_inv is on) calls getDefaultWATpl/formatWAMsg/
//     logWAMessage/sendWA — now resolved, see wa-shared.js (Phase 3).
//     recurring.php loads wa-shared.js alongside this file. Kept the
//     try/catch as a defensive safety net regardless.
//  3. Same function also called renderInvoicesTable()/
//     renderDashRecent()/updateDashStats() at the end — dashboard
//     and invoices page functions that don't exist here. Guarded
//     with typeof checks; this page has its own STATE.invoices
//     refresh regardless.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'settings']);
  await recLoadAll();
  renderRecurringPage();
  updateRecurringBadge();

  // Arrived from invoices.js's openRecurringFromInvoice() (Phase 3) —
  // pre-fill a new schedule from that invoice's client/items instead of
  // the SPA's original inline modal-fill (which shared DOM with
  // invoices.php and no longer applies across a real page navigation).
  const params = new URLSearchParams(window.location.search);
  const fromInvoiceId = params.get('from_invoice');
  if (fromInvoiceId) {
    const inv = STATE.invoices.find(i => String(i.id) === String(fromInvoiceId));
    if (inv) {
      await openRecurringModal(null);
      document.getElementById('rec-client').value = inv.client || '';
      recItems = [{ id: Date.now(), desc: inv.service || '', qty: 1, rate: parseFloat(inv.amount) || 0, gst: inv.gst !== undefined ? inv.gst : 18 }];
      recRenderItems();
      recCalcTotals();
      toast('Pre-filled from Invoice ' + (inv.num || fromInvoiceId), 'info');
    } else {
      toast('⚠️ Source invoice not found', 'warning');
    }
  }
});

let recItems = [];

function recNextDate(fromDate, freq) {
  const d = new Date(fromDate);
  switch (freq) {
    case 'weekly':     d.setDate(d.getDate() + 7);    break;
    case 'biweekly':   d.setDate(d.getDate() + 14);   break;
    case 'monthly':    d.setMonth(d.getMonth() + 1);  break;
    case 'quarterly':  d.setMonth(d.getMonth() + 3);  break;
    case 'halfyearly': d.setMonth(d.getMonth() + 6);  break;
    case 'yearly':     d.setFullYear(d.getFullYear() + 1); break;
    default:           d.setMonth(d.getMonth() + 1);
  }
  return d.toISOString().slice(0, 10);
}

function recFreqLabel(freq) {
  return {
    weekly: 'Weekly', biweekly: 'Bi-Weekly', monthly: 'Monthly',
    quarterly: 'Quarterly', halfyearly: 'Half-Yearly', yearly: 'Yearly',
  }[freq] || freq;
}

function recNormalizeRow(r) {
  return {
    id: r.id, clientId: r.client_id,
    clientName: r.client_name || r.client_name_joined || '',
    service: r.service || '', amount: parseFloat(r.amount) || 0,
    discType: r.disc_type || 'pct', discVal: parseFloat(r.disc_val) || 0,
    discPct: parseFloat(r.discount_pct) || 0, discAmt: parseFloat(r.discount_amt) || 0,
    gst: parseFloat(r.gst) || 0, gstAmt: parseFloat(r.gst_amt) || 0,
    grand: parseFloat(r.grand_total) || 0, items: Array.isArray(r.items) ? r.items : [],
    freq: r.freq || 'monthly', nextDate: r.next_date || '', endDate: r.end_date || '',
    dueDays: parseInt(r.due_days) || 15,
    template: r.template_id || STATE.settings.activeTemplate || '2',
    notes: r.notes || '', status: r.status || 'active',
    generatedCount: parseInt(r.generated_count) || 0,
    lastGenerated: r.last_generated || null, createdAt: r.created_at || '',
  };
}

async function recLoadAll() {
  try {
    const r = await api('/api/recurring.php');
    STATE.recurring = Array.isArray(r.data) ? r.data.map(recNormalizeRow) : [];
  } catch (e) {
    console.error('[Recurring] recLoadAll error:', e.message);
    STATE.recurring = STATE.recurring || [];
  }
  return STATE.recurring;
}

function recClientChange() {
  const clientId = document.getElementById('rec-client')?.value;
  const copyRow = document.getElementById('rec-copy-row');
  const copySelect = document.getElementById('rec-copy-select');
  if (!clientId) { if (copyRow) copyRow.style.display = 'none'; return; }
  const clientInvs = STATE.invoices
    .filter(i => String(i.client || i.client_id || i.clientId) === String(clientId) && !['Draft', 'Estimate', 'Cancelled'].includes(i.status))
    .sort((a, b) => new Date(b.issued || b.created_at || 0) - new Date(a.issued || a.created_at || 0));
  if (!clientInvs.length) { if (copyRow) copyRow.style.display = 'none'; return; }
  recFillFromInvoice(clientInvs[0]);
  if (copySelect) {
    copySelect.innerHTML = clientInvs.map((inv, idx) => {
      const num = inv.num || inv.invoice_number || 'Invoice';
      const amt = fmt_money(parseFloat(inv.amount || inv.grand_total || 0));
      const dt = inv.issued ? inv.issued.slice(0, 10) : '';
      return `<option value="${inv.id}" ${idx === 0 ? 'selected' : ''}>${num} — ${amt} ${dt ? '(' + dt + ')' : ''}</option>`;
    }).join('');
  }
  if (copyRow) copyRow.style.display = '';
}

function recFillFromInvoice(inv) {
  if (!inv) return;
  const srcItems = Array.isArray(inv.items) && inv.items.length ? inv.items : [];
  if (srcItems.length) {
    recItems = srcItems.map(i => ({
      id: Date.now() + Math.random(), desc: i.desc || i.description || '',
      qty: parseFloat(i.qty || i.quantity) || 1, rate: parseFloat(i.rate) || 0,
      gst: i.gst !== undefined && i.gst !== '' ? parseFloat(i.gst) : i.gstRate !== undefined ? parseFloat(i.gstRate) : 18,
    }));
  } else {
    const desc = inv.service_type || inv.svc || inv.service || 'Service';
    const rate = parseFloat(inv.subtotal || inv.amount || inv.grand_total) || 0;
    recItems = [{ id: Date.now(), desc, qty: 1, rate, gst: 18 }];
  }
  const rawDiscPct = parseFloat(inv.disc || inv.discount_pct) || 0;
  const rawDiscAmt = parseFloat(inv.discount_amt) || 0;
  const rawDiscType = inv.discount_type || ((rawDiscAmt > 0 && rawDiscPct === 0) ? 'fixed' : 'pct');
  const discType = rawDiscType === 'percent' ? 'pct' : rawDiscType;
  const discVal = discType === 'fixed' ? rawDiscAmt : rawDiscPct;
  const rdtEl = document.getElementById('rec-disc-type'); const rdEl = document.getElementById('rec-disc');
  if (rdtEl) rdtEl.value = discType; if (rdEl) rdEl.value = discVal || 0;
  if (inv.issued && inv.due) {
    const diff = Math.round((new Date(inv.due) - new Date(inv.issued)) / 864e5);
    if (diff > 0) { const dueDaysEl = document.getElementById('rec-due-days'); if (dueDaysEl) dueDaysEl.value = diff; }
  }
  const tplEl = document.getElementById('rec-template');
  if (tplEl && (inv.template || inv.template_id)) tplEl.value = String(inv.template || inv.template_id);
  if (inv.notes) { const notesEl = document.getElementById('rec-notes'); if (notesEl && !notesEl.value) notesEl.value = inv.notes; }
  recRenderItems(); recCalcTotals();
}

function recCopyFromInvoice(invId) {
  const inv = STATE.invoices.find(i => String(i.id) === String(invId));
  if (inv) recFillFromInvoice(inv);
}

function recFreqChange() {
  const freq = document.getElementById('rec-freq')?.value || 'monthly';
  const start = document.getElementById('rec-start')?.value || '';
  const endDate = document.getElementById('rec-end')?.value || '';
  const dueDays = parseInt(document.getElementById('rec-due-days')?.value) || 15;
  const setEl = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  if (start) {
    const next = recNextDate(start, freq);
    const dueD = new Date(start); dueD.setDate(dueD.getDate() + dueDays);
    setEl('rec-prev-first', start); setEl('rec-prev-next', next);
    setEl('rec-prev-due', dueD.toISOString().slice(0, 10) + ` (+${dueDays}d)`);
    if (endDate && endDate >= start) {
      let count = 0, cur = start;
      while (cur <= endDate && count < 600) { count++; cur = recNextDate(cur, freq); }
      setEl('rec-prev-count', count + ' invoices');
      const ovEl = document.getElementById('rec-tot-count-label'); if (ovEl) ovEl.textContent = `× ${count} invoices`;
      recUpdateOverallTotal(count);
    } else {
      setEl('rec-prev-count', '∞ (no end date)');
      const ovEl = document.getElementById('rec-tot-count-label'); if (ovEl) ovEl.textContent = '× ∞ (no end date)';
      const ovTot = document.getElementById('rec-tot-overall'); if (ovTot) ovTot.textContent = '—';
    }
  } else {
    setEl('rec-prev-first', '—'); setEl('rec-prev-next', '—'); setEl('rec-prev-due', '—'); setEl('rec-prev-count', '—');
  }
}

function recUpdateOverallTotal(count) {
  if (!count) return;
  let sub = 0, gstTotal = 0;
  recItems.forEach(item => { const line = (item.qty || 1) * (item.rate || 0); sub += line; gstTotal += line * (item.gst || 0) / 100; });
  const discType = document.getElementById('rec-disc-type')?.value || 'pct';
  const discVal = parseFloat(document.getElementById('rec-disc')?.value) || 0;
  const discAmt = discType === 'fixed' ? Math.min(discVal, sub) : sub * discVal / 100;
  const discFactor = sub > 0 ? (1 - discAmt / sub) : 1;
  const grand = sub - discAmt + (gstTotal * discFactor);
  const overall = grand * count;
  const fmt = v => '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  const ovTot = document.getElementById('rec-tot-overall');
  if (ovTot) ovTot.textContent = overall > 0 ? fmt(overall) + ' total' : '—';
}

function recGoStep(step) {
  const s1 = document.getElementById('rec-step-1'), s2 = document.getElementById('rec-step-2');
  const dot1 = document.getElementById('rec-step-dot-1'), dot2 = document.getElementById('rec-step-dot-2');
  const lbl = document.getElementById('rec-step-label');
  const btnBack = document.getElementById('rec-btn-back'), btnCancel = document.getElementById('rec-btn-cancel');
  const btnNext = document.getElementById('rec-btn-next'), btnSave = document.getElementById('rec-btn-save');
  if (step === 2) {
    const clientId = document.getElementById('rec-client')?.value;
    const start = document.getElementById('rec-start')?.value;
    if (!clientId) { const cl = document.getElementById('rec-client'); if (cl) { cl.style.border = '1.5px solid var(--red)'; cl.focus(); setTimeout(() => cl.style.border = '', 2000); } toast('⚠️ Please select a client', 'warning'); return; }
    if (!start) { const sd = document.getElementById('rec-start'); if (sd) { sd.style.border = '1.5px solid var(--red)'; sd.focus(); setTimeout(() => sd.style.border = '', 2000); } toast('⚠️ Please set a start date', 'warning'); return; }
    s1.style.display = 'none'; s2.style.display = 'flex';
    dot1.style.background = 'var(--teal)'; dot2.style.background = 'var(--teal)';
    lbl.textContent = 'Step 2 of 2 — Billing';
    btnBack.style.display = ''; btnCancel.style.display = 'none'; btnNext.style.display = 'none'; btnSave.style.display = '';
    recCalcTotals(); recFreqChange();
  } else {
    s1.style.display = 'flex'; s2.style.display = 'none';
    dot1.style.background = 'var(--teal)'; dot2.style.background = 'var(--border)';
    lbl.textContent = 'Step 1 of 2 — Schedule';
    btnBack.style.display = 'none'; btnCancel.style.display = ''; btnNext.style.display = ''; btnSave.style.display = 'none';
  }
}

async function openRecurringModal(id) {
  const sel = document.getElementById('rec-client');
  if (sel) sel.innerHTML = '<option value="">— Select Client —</option>' + STATE.clients.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  const today = new Date().toISOString().slice(0, 10);

  if (id) {
    let s = STATE.recurring.find(x => String(x.id) === String(id));
    if (!s) {
      try { const r = await api('/api/recurring.php?id=' + encodeURIComponent(id)); s = recNormalizeRow(r.data); }
      catch (e) { toast('⚠️ Could not load schedule: ' + e.message, 'error'); return; }
    }
    document.getElementById('rec-modal-title').textContent = `Edit Schedule — ${s.clientName || ''} (${recFreqLabel(s.freq || 'monthly')})`;
    const _crEdit = document.getElementById('rec-copy-row'); if (_crEdit) _crEdit.style.display = 'none';
    document.getElementById('rec-edit-id').value = s.id;
    document.getElementById('rec-client').value = s.clientId || '';
    document.getElementById('rec-freq').value = s.freq || 'monthly';
    document.getElementById('rec-start').value = s.nextDate || today;
    document.getElementById('rec-end').value = s.endDate || '';
    document.getElementById('rec-due-days').value = s.dueDays || 15;
    document.getElementById('rec-template').value = String(s.template || s.template_id || STATE.settings.activeTemplate || '2');
    document.getElementById('rec-notes').value = s.notes || '';
    if (s.items && s.items.length) recItems = s.items.map(i => ({ ...i, id: Date.now() + Math.random() }));
    else recItems = [{ id: Date.now(), desc: s.service || '', qty: 1, rate: s.amount || 0, gst: s.gst !== undefined ? s.gst : 18 }];
    recRenderItems();
    const rdtEl = document.getElementById('rec-disc-type'); if (rdtEl) rdtEl.value = s.discType || 'pct';
    const rdEl = document.getElementById('rec-disc'); if (rdEl) rdEl.value = s.discVal || 0;
    recCalcTotals();
  } else {
    document.getElementById('rec-modal-title').textContent = 'New Recurring Schedule';
    document.getElementById('rec-edit-id').value = '';
    document.getElementById('rec-client').value = '';
    document.getElementById('rec-freq').value = 'monthly';
    document.getElementById('rec-start').value = today;
    document.getElementById('rec-end').value = '';
    document.getElementById('rec-due-days').value = String(STATE.settings.dueDays || 15);
    document.getElementById('rec-template').value = String(STATE.settings.activeTemplate || '2');
    document.getElementById('rec-notes').value = '';
    const rdtEl2 = document.getElementById('rec-disc-type'); if (rdtEl2) rdtEl2.value = 'pct';
    const rdEl2 = document.getElementById('rec-disc'); if (rdEl2) rdEl2.value = 0;
    const _cr = document.getElementById('rec-copy-row'); if (_cr) _cr.style.display = 'none';
    recItems = []; recAddItem(); recCalcTotals();
  }
  recFreqChange();
  recGoStep(1);
  openModal('modal-recurring');
}

function recAddItem(item) {
  const id = Date.now() + Math.random();
  recItems.push({ id, desc: item?.desc || '', qty: item?.qty || 1, rate: item?.rate || 0, gst: item?.gst !== undefined ? item.gst : 18 });
  recRenderItems(); recCalcTotals();
}
function recRemoveItem(id) { recItems = recItems.filter(x => x.id !== id); recRenderItems(); recCalcTotals(); }

function recRenderItems() {
  const list = document.getElementById('rec-items-list');
  if (!list) return;
  list.innerHTML = recItems.map(item => `
    <div style="display:grid;grid-template-columns:1fr 70px 100px 80px 30px;border-top:1px solid var(--border);align-items:center">
      <input value="${item.desc}" placeholder="Description" style="border:none;background:transparent;padding:8px 10px;font-size:13px;outline:none;width:100%" oninput="recItems.find(x=>x.id===${item.id}).desc=this.value">
      <input type="number" value="${item.qty}" min="1" style="border:none;background:transparent;padding:8px 6px;font-size:13px;outline:none;text-align:center;width:100%" oninput="recItems.find(x=>x.id===${item.id}).qty=parseFloat(this.value)||1;recCalcTotals()">
      <input type="number" value="${item.rate}" min="0" step="0.01" style="border:none;background:transparent;padding:8px 6px;font-size:13px;outline:none;text-align:right;width:100%" oninput="recItems.find(x=>x.id===${item.id}).rate=parseFloat(this.value)||0;recCalcTotals()">
      <select style="border:none;background:transparent;padding:8px 4px;font-size:12px;outline:none;width:100%" onchange="recItems.find(x=>x.id===${item.id}).gst=parseFloat(this.value);recCalcTotals()">
        ${[0, 5, 12, 18, 28].map(g => `<option value="${g}"${g === item.gst ? ' selected' : ''}>${g}%</option>`).join('')}
      </select>
      <button onclick="recRemoveItem(${item.id})" style="border:none;background:transparent;color:var(--red);cursor:pointer;padding:4px;font-size:14px" title="Remove">×</button>
    </div>`).join('');
}

function recCalcTotals() {
  let sub = 0, gstTotal = 0;
  recItems.forEach(item => { const line = (item.qty || 1) * (item.rate || 0); sub += line; gstTotal += line * (item.gst || 0) / 100; });
  const discType = document.getElementById('rec-disc-type')?.value || 'pct';
  const discVal = parseFloat(document.getElementById('rec-disc')?.value) || 0;
  const discAmt = discType === 'fixed' ? Math.min(discVal, sub) : sub * discVal / 100;
  const discFactor = sub > 0 ? (1 - discAmt / sub) : 1;
  const gstAfterDisc = gstTotal * discFactor;
  const grand = sub - discAmt + gstAfterDisc;
  const fmt = v => '₹' + v.toLocaleString(_moneyLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  set('rec-tot-sub', fmt(sub)); set('rec-tot-disc', '-' + fmt(discAmt)); set('rec-tot-gst', fmt(gstAfterDisc)); set('rec-tot-grand', fmt(grand));
  const countEl = document.getElementById('rec-prev-count');
  if (countEl) { const countNum = parseInt(countEl.textContent); if (!isNaN(countNum)) recUpdateOverallTotal(countNum); }
}

async function saveRecurring() {
  const clientId = document.getElementById('rec-client').value;
  const freq = document.getElementById('rec-freq').value;
  const start = document.getElementById('rec-start').value;
  const endDate = document.getElementById('rec-end').value || '';
  const dueDays = parseInt(document.getElementById('rec-due-days').value) || 15;
  const template = document.getElementById('rec-template').value || STATE.settings.activeTemplate || '2';
  const notes = document.getElementById('rec-notes').value.trim();
  const editId = document.getElementById('rec-edit-id').value;
  const discType = document.getElementById('rec-disc-type')?.value || 'pct';
  const discVal = parseFloat(document.getElementById('rec-disc')?.value) || 0;

  if (!clientId) { toast('⚠️ Please select a client', 'warning'); return; }
  if (!recItems.length) { toast('⚠️ Add at least one line item', 'warning'); return; }
  if (recItems.some(i => !i.desc.trim())) { toast('⚠️ All items need a description', 'warning'); return; }
  if (!start) { toast('⚠️ Please set a start date', 'warning'); return; }

  const client = STATE.clients.find(c => String(c.id) === String(clientId));
  let sub = 0, gstTotal = 0;
  recItems.forEach(item => { const line = (item.qty || 1) * (item.rate || 0); sub += line; gstTotal += line * (item.gst || 0) / 100; });
  const discAmt = discType === 'fixed' ? Math.min(discVal, sub) : sub * discVal / 100;
  const discPct = sub > 0 ? (discAmt / sub * 100) : 0;
  const discFactor = sub > 0 ? (1 - discAmt / sub) : 1;
  const gstAmt = gstTotal * discFactor;
  const grand = sub - discAmt + gstAmt;
  const service = recItems.map(i => i.desc).join(', ');

  const payload = {
    clientId, clientName: client?.name || '', service, amount: sub,
    discType, discVal, discPct, discAmt, gst: 0, gstAmt, grand,
    items: recItems.map(({ id, ...rest }) => rest),
    freq, nextDate: start, endDate, dueDays, template, notes,
  };

  const btn = document.querySelector('#modal-recurring .btn-primary');
  if (btn) btn.disabled = true;
  try {
    if (editId) { await api('/api/recurring.php?id=' + encodeURIComponent(editId), 'PUT', payload); toast('✅ Schedule updated!', 'success'); }
    else { await api('/api/recurring.php', 'POST', payload); toast('✅ Recurring schedule created!', 'success'); }
    closeModal('modal-recurring');
    await recLoadAll();
    renderRecurringPage();
    updateRecurringBadge();
  } catch (e) { toast('❌ Save failed: ' + e.message, 'error'); }
  finally { if (btn) btn.disabled = false; }
}

async function runRecurringCheck() {
  const schedules = STATE.recurring;
  const today = new Date().toISOString().slice(0, 10);
  let generated = 0;

  for (const s of schedules) {
    if (s.status !== 'active') continue;
    if (!s.nextDate || today < s.nextDate) continue;

    try {
      const client = STATE.clients.find(c => String(c.id) === String(s.clientId));
      const issueDate = s.nextDate;
      const dueDate = (() => { const d = new Date(issueDate); d.setDate(d.getDate() + (s.dueDays || 15)); return d.toISOString().slice(0, 10); })();
      const _recPfx = STATE.settings.prefix || STATE.settings.invoice_prefix || STATE.settings.invoicePrefix || ('INV-' + new Date().getFullYear() + '-');
      let _recSeq = 1;
      STATE.invoices.forEach(inv => {
        const n = inv.num || inv.invoice_number || '';
        if (n.startsWith(_recPfx)) { const _s = parseInt(n.slice(_recPfx.length), 10); if (!isNaN(_s) && _s >= _recSeq) _recSeq = _s + 1; }
      });
      const invoiceNum = _recPfx + String(_recSeq).padStart(3, '0');

      const recInvItems = (s.items && s.items.length)
        ? s.items.map(i => ({ desc: i.desc, itemType: 'Service', qty: parseFloat(i.qty) || 1, rate: parseFloat(i.rate) || 0, gst: parseFloat(i.gst) || 0 }))
        : [{ desc: s.service, itemType: 'Service', qty: 1, rate: s.amount, gst: s.gst || 0 }];

      let recSub = 0, recGstRaw = 0;
      recInvItems.forEach(item => { const line = item.qty * item.rate; recSub += line; recGstRaw += line * item.gst / 100; });
      const recDiscAmt = s.discType === 'fixed' ? Math.min(s.discVal || 0, recSub) : recSub * (s.discVal || 0) / 100;
      const recDiscFactor = recSub > 0 ? (1 - recDiscAmt / recSub) : 1;
      const recGstAmt = recGstRaw * recDiscFactor;
      const recGrand = recSub - recDiscAmt + recGstAmt;

      const savedPopt = STATE.settings.popt_prefs || {};
      const recPopt = Object.assign({ bank: true, qr: false, sign: true, logo: true, clientLogo: false, notes: true, tnc: true, gstCol: true, footer: true, watermark: false }, savedPopt);

      const invoicePayload = {
        invoice_number: invoiceNum, client_id: client ? parseInt(s.clientId) : null,
        client_name: s.clientName || '', service_type: recInvItems.map(i => i.desc).join(', '),
        issued_date: issueDate, due_date: dueDate, status: 'Pending', currency: '₹',
        subtotal: recSub, discount_pct: recDiscPct, discount_amt: recDiscAmt,
        gst_amount: recGstAmt, grand_total: recGrand,
        notes: s.notes || `Auto-generated recurring invoice (${recFreqLabel(s.freq)})`,
        bank_details: STATE.settings.defaultBank || '', terms: STATE.settings.defaultTnC || '',
        company_logo: STATE.settings.logo || '', client_logo: '', signature: STATE.settings.signature || '',
        qr_code: '', template_id: parseInt(s.template || s.template_id || STATE.settings.activeTemplate || 2),
        generated_by: (STATE.settings.company ? STATE.settings.company + ' — Recurring' : 'Recurring Invoice'),
        show_generated: 1, pdf_options: recPopt, items: recInvItems,
      };

      const _recInvResult = await api('/api/invoices.php', 'POST', invoicePayload);

      // Auto-fire WA if enabled — wa-shared.js (Phase 3) provides this
      // now; kept the try/catch as a defensive safety net so one
      // schedule's WA failure doesn't abort the rest of the run.
      const _recWA = STATE.settings.wa || {};
      if (_recWA.auto_inv === '1') {
        try {
          const _recInvObj = {
            id: _recInvResult?.id || _recInvResult?.data?.id || null, num: invoiceNum, invoice_number: invoiceNum,
            client: s.clientId, clientName: s.clientName, client_name: s.clientName,
            amount: recGrand, grand_total: recGrand, status: 'Pending', issued: issueDate, due: dueDate,
            currency: '₹', service: recInvItems.map(i => i.desc).join(', '),
          };
          const _prevUnpaid = STATE.invoices.filter(i => String(i.client || i.client_id || i.clientId) === String(s.clientId) && ['Pending', 'Overdue', 'Partial'].includes(i.status) && (i.num || i.invoice_number) !== invoiceNum);
          const _prevTotal = _prevUnpaid.reduce((sum, i) => sum + parseFloat(i.amount || i.grand_total || 0), 0);
          const _totalPayable = _prevTotal + recGrand;
          _recInvObj._outstandingDues = _prevUnpaid.length > 0
            ? `──────────────────\n⚠️ *Previous Outstanding Dues:*\n` + _prevUnpaid.map(i => `  • ${i.num || i.invoice_number || 'Invoice'} — ${fmt_money(parseFloat(i.amount || i.grand_total || 0))} (${i.status})`).join('\n') + `\n💰 *Total Payable (incl. this invoice): ${fmt_money(_totalPayable)}*\nPlease clear all dues at earliest. 🙏`
            : '';
          _recInvObj._totalPayable = _prevUnpaid.length > 0 ? _totalPayable : null;

          setTimeout(() => {
            try {
              const _c = STATE.clients.find(x => String(x.id) === String(s.clientId)) || {};
              const _email = _c.email || _c.mail || '';
              const _phone = (_c.wa || _c.whatsapp || _c.phone || '').replace(/\D/g, '');
              const wa = STATE.settings.wa || {};
              if (_phone) {
                const tpl = wa.tpl_recurring || getDefaultWATpl('recurring');
                const msg = formatWAMsg(tpl, _recInvObj, _c, STATE.settings);
                logWAMessage({ inv: _recInvObj, client: _c, type: 'invoice_created', msg, status: 'sending' });
                sendWA(_phone, msg, 'invoice_created', _recInvObj, _c)
                  .then(res => logWAMessage({ inv: _recInvObj, client: _c, type: 'invoice_created', msg, status: res ? 'sent_api' : 'sent_web' }))
                  .catch(e => logWAMessage({ inv: _recInvObj, client: _c, type: 'invoice_created', msg, status: 'failed', error: e.message }));
              }
              const ec = STATE.settings.email_cfg || {};
              if (_email && ec.email_auto_inv === '1' && (ec.smtp_host || ec.smtp_user)) {
                const invId = _recInvResult?.id || _recInvResult?.data?.id || null;
                if (invId) {
                  api('/api/email.php?action=send', 'POST', { action: 'send', to: _email, to_name: _c.name || s.clientName || '', invoice_id: invId, type: 'recurring' })
                    .then(r => { if (!r?.success) console.warn('[Recurring Email] Failed:', r?.error); })
                    .catch(e => console.warn('[Recurring Email] Error:', e.message));
                }
              }
            } catch (e) { console.warn('[Recurring WA/Email auto-send] not available yet:', e.message); }
          }, 800);
        } catch (e) { console.warn('[Recurring WA] setup skipped:', e.message); }
      }

      const newNextDate = recNextDate(s.nextDate, s.freq);
      const newGeneratedCount = (s.generatedCount || 0) + 1;
      await api('/api/recurring.php?id=' + encodeURIComponent(s.id), 'PATCH', { nextDate: newNextDate, generatedCount: newGeneratedCount, lastGenerated: issueDate });
      s.nextDate = newNextDate; s.generatedCount = newGeneratedCount; s.lastGenerated = issueDate;
      generated++;

      if (s.endDate && today >= s.endDate) {
        try { await api('/api/recurring.php?id=' + encodeURIComponent(s.id), 'PATCH', { status: 'completed' }); s.status = 'completed'; }
        catch (e) { console.warn('[Recurring] Could not mark completed:', e.message); }
      }
    } catch (e) {
      console.error('[Recurring] Generation failed for schedule', s.id, e.message);
      toast('⚠️ Failed to generate for ' + s.clientName + ': ' + e.message, 'error');
    }
  }

  if (generated > 0) {
    const r = await api('/api/invoices.php');
    STATE.invoices = Array.isArray(r.data) ? r.data.map(normalizeInvoice) : [];
    STATE.filteredInvoices = [...STATE.invoices];
    // These belong to invoices.php/dashboard.php, not this page —
    // guarded so this page doesn't depend on either being loaded.
    if (typeof renderInvoicesTable === 'function') renderInvoicesTable();
    if (typeof renderDashRecent === 'function') renderDashRecent();
    if (typeof updateDashStats === 'function') updateDashStats();
    toast(`✅ ${generated} invoice${generated > 1 ? 's' : ''} generated!`, 'success');
  } else {
    toast('ℹ️ No invoices due today', 'info');
  }
  renderRecurringPage();
  updateRecurringBadge();
}

function renderRecurringPage() {
  const schedules = STATE.recurring;
  const today = new Date().toISOString().slice(0, 10);
  const tbody = document.getElementById('rec-table-body');
  const empty = document.getElementById('rec-empty');
  if (!tbody) return;

  const active = schedules.filter(s => s.status === 'active').length;
  const dueToday = schedules.filter(s => s.status === 'active' && s.nextDate <= today).length;
  const paused = schedules.filter(s => s.status === 'paused').length;
  const generated = schedules.reduce((a, s) => a + (s.generatedCount || 0), 0);

  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  set('rec-stat-active', active); set('rec-stat-due', dueToday); set('rec-stat-generated', generated); set('rec-stat-paused', paused);

  if (!schedules.length) { tbody.innerHTML = ''; if (empty) empty.style.display = ''; return; }
  if (empty) empty.style.display = 'none';

  const statusChip = s => {
    if (s.status === 'paused') return `<span style="padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#FEF3C7;color:#92400E">Paused</span>`;
    if (s.status === 'completed') return `<span style="padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#E8F5E9;color:#388E3C">Completed</span>`;
    if (s.nextDate <= today) return `<span style="padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#FEE2E2;color:#C62828;animation:pulse 1.5s infinite">Due Today!</span>`;
    return `<span style="padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#E0F2F1;color:#00695C">Active</span>`;
  };

  tbody.innerHTML = schedules.map(s => `
    <tr>
      <td style="font-weight:700">${s.clientName || '—'}</td>
      <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${s.service}">${s.service}</td>
      <td style="font-family:var(--mono);font-weight:700">₹${parseFloat(s.grand || s.amount || 0).toLocaleString(_moneyLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      <td><span style="padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:var(--blue-bg);color:var(--blue)">${recFreqLabel(s.freq)}</span></td>
      <td style="font-family:var(--mono);${s.nextDate <= today && s.status === 'active' ? 'color:var(--red);font-weight:700' : ''}">${s.nextDate || '—'}</td>
      <td style="font-family:var(--mono);color:var(--muted)">${s.lastGenerated || 'Never'}</td>
      <td>${statusChip(s)}</td>
      <td style="text-align:center;font-weight:700;color:var(--teal)">${s.generatedCount || 0}</td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="act-btn" title="Edit" onclick="openRecurringModal('${s.id}')"><i class="fas fa-edit"></i></button>
          <button class="act-btn" title="${s.status === 'paused' ? 'Resume' : 'Pause'}" onclick="recPause('${s.id}')"><i class="fas fa-${s.status === 'paused' ? 'play' : 'pause'}"></i></button>
          <button class="act-btn" title="Delete" onclick="recDelete('${s.id}')" style="color:var(--red)"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>`).join('');
}

function updateRecurringBadge() {
  const today = new Date().toISOString().slice(0, 10);
  const due = (STATE.recurring || []).filter(s => s.status === 'active' && s.nextDate <= today).length;
  const badge = document.getElementById('badge-recurring');
  if (badge) { badge.textContent = due; badge.style.display = due ? '' : 'none'; }
}

async function recPause(id) {
  const s = STATE.recurring.find(x => String(x.id) === String(id)); if (!s) return;
  const newStatus = s.status === 'paused' ? 'active' : 'paused';
  try {
    await api('/api/recurring.php?id=' + encodeURIComponent(id), 'PATCH', { status: newStatus });
    s.status = newStatus; renderRecurringPage(); updateRecurringBadge();
    toast(newStatus === 'paused' ? '⏸ Schedule paused' : '▶ Schedule resumed', 'info');
  } catch (e) { toast('❌ Could not update status: ' + e.message, 'error'); }
}

async function recDelete(id) {
  const result = await Swal.fire({ title: 'Delete Recurring Schedule?', text: 'Already-generated invoices will not be deleted.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!result.isConfirmed) return;
  try {
    await api('/api/recurring.php?id=' + encodeURIComponent(id), 'DELETE');
    STATE.recurring = STATE.recurring.filter(x => String(x.id) !== String(id));
    renderRecurringPage(); updateRecurringBadge();
    toast('🗑 Schedule deleted', 'info');
  } catch (e) { toast('❌ Delete failed: ' + e.message, 'error'); }
}

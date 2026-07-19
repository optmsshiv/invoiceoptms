// ================================================================
//  assets/js/invoice-paid-shared.js
//  Requires: common.js, shared-data.js, wa-shared.js (loaded before
//  this file).
//
//  The "Mark as Paid" modal — genuinely shared between:
//    - pages/invoices/create.php's markFormPaid() (marking the
//      current, possibly-unsaved form as paid — passes id=null,
//      which falls back to reading the live form via getFormData())
//    - pages/invoices/invoices.php's row-menu "Mark Paid" action
//      (not wired up yet on that page — pre-existing gap, not
//      introduced here; adding this file to its pageScripts would
//      be most of the fix once you're ready for it)
//
//  MPA FIX: the old SPA's confirmPaid() ended by directly calling
//  renderInvoicesTable(), renderDonutChart(), renderDashRecent(),
//  renderPayments(), updateDashStats(), renderDashKpis() to refresh
//  every view that might show invoice/payment data — since it was
//  all one page, all of those always existed. In the MPA, at most
//  one or two of those exist on any given page, so calling all six
//  unconditionally would throw "X is not defined". Wrapped in a
//  typeof guard so each page just refreshes whichever of its own
//  render functions actually exist, silently skipping the rest.
// ================================================================

function openPaidModal(id) {
  STATE.activeMenuInvoiceId = String(id || STATE.activeMenuInvoiceId);

  // ── ADD THIS: reset confirm button in case previous payment left it in loading state ──
  const confirmBtn = document.getElementById('btn-confirm-paid');
  if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Payment'; }

  // Reset payment form
  document.getElementById('paid-date').value = fmt_date(new Date());
  const _now = new Date();
  document.getElementById('paid-time').value = String(_now.getHours()).padStart(2,'0') + ':' + String(_now.getMinutes()).padStart(2,'0');
  const _timeDisp = document.getElementById('paid-time-display');
  if (_timeDisp) {
    const _h12 = ((_now.getHours() % 12) || 12);
    const _ampm = _now.getHours() < 12 ? 'AM' : 'PM';
    _timeDisp.innerHTML = '<i class="fas fa-clock" style="margin-right:4px;opacity:.7"></i>' + _h12 + ':' + String(_now.getMinutes()).padStart(2,'0') + ' ' + _ampm;
  }
  document.getElementById('paid-txn').value  = '';
  document.getElementById('paid-notes').value = '';
  const sdEl = document.getElementById('paid-settle-disc'); if (sdEl) { sdEl.value = '0'; sdEl.dataset.wasApplied = ''; }
  const sdtEl = document.getElementById('paid-settle-disc-type'); if (sdtEl) sdtEl.value = 'pct';
  const sdDisp = document.getElementById('paid-settle-disc-display'); if (sdDisp) { sdDisp.style.display='none'; sdDisp.textContent=''; }
  const sdInfo = document.getElementById('paid-settle-disc-info'); if (sdInfo) { sdInfo.style.display='none'; sdInfo.textContent=''; }
  document.getElementById('paid-remaining-box').style.display = 'none';
  // Reset split payment panel — clear amounts to zero, hide panel
  const splitPanel = document.getElementById('split-payment-panel');
  if (splitPanel) splitPanel.style.display = 'none';
  document.querySelectorAll('#split-rows .split-amt').forEach(el => { el.value = ''; });
  const splitTotal = document.getElementById('split-total');
  if (splitTotal) splitTotal.textContent = '₹0.00';
  const methodSel = document.getElementById('paid-method');
  if (methodSel) methodSel.selectedIndex = 0;
  // Re-enable amount field (may have been dimmed by split mode)
  const amtFld = document.getElementById('paid-amt-field');
  if (amtFld) amtFld.style.opacity = '1';

  const inv = STATE.invoices.find(i=>String(i.id)===String(STATE.activeMenuInvoiceId));
  const c   = inv ? (STATE.clients.find(x=>String(x.id)===String(inv.client))||{}) : {};
  const amt = inv ? parseFloat(inv.amount||0) : parseFloat(getFormData().grand||0);
  const sym = inv ? (inv.currency||'₹') : '₹';

  // Calculate already paid for this invoice
  const alreadyPaid = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === STATE.activeMenuInvoiceId)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  const remaining = Math.max(0, amt - alreadyPaid);

  // Pre-fill amount with what's still due; clear user-edited flag so discount can auto-fill
  const amtFieldEl = document.getElementById('paid-amt');
  amtFieldEl.value = (remaining > 0 ? remaining : amt).toFixed(2);
  amtFieldEl.dataset.userEdited = '';
  amtFieldEl.dataset.autoValue = amtFieldEl.value;

  // Show already-paid + remaining in summary bar
  const remRow = document.getElementById('paid-inv-remaining-row');
  const alreadyEl = document.getElementById('paid-inv-already');
  const remainingEl = document.getElementById('paid-inv-remaining');
  if (remRow) {
    if (alreadyPaid > 0.01) {
      remRow.style.display = 'flex';
      if (alreadyEl) alreadyEl.textContent = fmt_money(alreadyPaid, sym);
      if (remainingEl) remainingEl.textContent = fmt_money(remaining, sym);
    } else {
      remRow.style.display = 'none';
    }
  }

  // If already partially paid, show partial box with checkbox pre-checked
  if (alreadyPaid > 0.01 && remaining > 0.01) {
    const rb = document.getElementById('paid-remaining-box');
    if (rb) {
      rb.style.display = 'block';
      const rt = document.getElementById('paid-rem-total');
      const rr = document.getElementById('paid-rem-received');
      const rd = document.getElementById('paid-rem-due');
      if (rt) rt.textContent = fmt_money(amt, sym);
      if (rr) rr.textContent = fmt_money(alreadyPaid, sym);
      if (rd) rd.textContent = fmt_money(remaining, sym);
      // Fix: also update pct badge and progress bar on modal open
      const openPct = amt > 0 ? Math.min(100, Math.round(alreadyPaid / amt * 100)) : 0;
      const pctEl = document.getElementById('paid-rem-pct');
      const barEl = document.getElementById('paid-rem-bar');
      if (pctEl) pctEl.textContent = openPct + '%';
      if (barEl) barEl.style.width = openPct + '%';
      const cb = document.getElementById('paid-collect-remaining');
      if (cb) cb.checked = true;
    }
  }

  // Summary bar
  const numEl = document.getElementById('paid-inv-num');
  const cliEl = document.getElementById('paid-inv-client');
  const totEl = document.getElementById('paid-inv-total');
  if (numEl) numEl.textContent = inv ? (inv.num||inv.invoice_number||'') : '';
  if (cliEl) cliEl.textContent = c.name || (inv&&inv.client_name) || '';
  if (totEl) totEl.textContent = fmt_money(amt, sym);

  const hdr = document.getElementById('paid-inv-subtitle');
  if (hdr) hdr.textContent = inv&&inv.status==='Partial' ? 'Collect remaining payment' : 'Mark invoice as paid';
  openModal('modal-paid');
}

// Called when user types directly in Amount Received — ignored when split mode is active
function onPaidAmtInput() {
  const isSplit = document.getElementById('paid-method')?.value === 'Split';
  if (isSplit) {
    // When total amount changes in split mode, redistribute: fill row 0 with full amount, clear row 1 so user re-adjusts
    const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
    const rows = document.querySelectorAll('#split-rows .split-amt');
    if (rows.length >= 2) {
      rows[0].value = totalAmt > 0 ? totalAmt.toFixed(2) : '';
      rows[1].value = '';
    }
    updateSplitTotal();
    renderSplitBreakdown();
    return;
  }
  // Mark field as manually edited — but only if the value actually differs from
  // the amount the discount logic last auto-filled (retyping the same value shouldn't lock it)
  const amtElB = document.getElementById('paid-amt');
  if (amtElB) {
    const typed = parseFloat(amtElB.value) || 0;
    const auto  = parseFloat(amtElB.dataset.autoValue) || 0;
    amtElB.dataset.userEdited = (Math.abs(typed - auto) > 0.001) ? 'true' : '';
  }
  updatePaidRemaining();
}

// Get computed settlement discount amount from modal inputs
function getSettlementDiscAmt(totalAmt) {
  const discType = document.getElementById('paid-settle-disc-type')?.value || 'pct';
  const discVal  = parseFloat(document.getElementById('paid-settle-disc')?.value) || 0;
  if (!discVal) return 0;
  return discType === 'fixed' ? Math.min(discVal, totalAmt) : totalAmt * discVal / 100;
}

// Called when settlement discount input changes
function onPaidSettleDiscInput() {
  const mid = STATE.activeMenuInvoiceId;
  const inv = STATE.invoices.find(i => String(i.id) === mid);
  if (!inv) return;
  const sym      = inv.currency || '₹';
  const totalAmt = parseFloat(inv.amount || 0);
  const discAmt  = getSettlementDiscAmt(totalAmt);
  const dispEl   = document.getElementById('paid-settle-disc-display');
  const infoEl   = document.getElementById('paid-settle-disc-info');
  const noteEl   = document.getElementById('paid-amt-label-note');

  // Discount is zero — clear discount UI; only recalculate banner if breakdown was previously shown
  if (discAmt < 0.001) {
    if (dispEl) { dispEl.style.display = 'none'; dispEl.textContent = ''; }
    if (infoEl) { infoEl.style.display = 'none'; infoEl.textContent = ''; }
    if (noteEl) noteEl.textContent = '';
    // Only restore the amount if a discount was actually applied before (not on initial load
    // or when toggling the % / Fixed dropdown while the value is already 0)
    const discInputEl = document.getElementById('paid-settle-disc');
    const wasApplied   = discInputEl?.dataset.wasApplied === 'true';
    if (wasApplied) {
      // Reset amount field back to remaining due (undo the discount auto-fill) unless user manually edited it
      const amtEl = document.getElementById('paid-amt');
      if (amtEl && amtEl.dataset.userEdited !== 'true') {
        const prevPaidReset = STATE.payments
          .filter(p => p.invoice_id && String(p.invoice_id) === mid)
          .reduce((s,p) => s + parseFloat(p.amount||0), 0);
        const remainingDueReset = Math.max(0, totalAmt - prevPaidReset);
        amtEl.value = remainingDueReset.toFixed(2);
        amtEl.dataset.autoValue = amtEl.value;
      }
      if (discInputEl) discInputEl.dataset.wasApplied = '';
      updatePaidRemaining();
    }
    return;
  }
  // Compute remaining due (after any prior partial payments) — used in both branches
  const prevPaidCheck = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === mid)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  const remainingDue = Math.max(0, totalAmt - prevPaidCheck);

  if (discAmt > 0.001) {
    // effAmt = cash client actually pays = remaining due minus written-off discount
    const effAmt = Math.max(0, remainingDue - discAmt);
    if (dispEl) { dispEl.textContent = '-' + fmt_money(discAmt, sym); dispEl.style.display = 'block'; }
    if (infoEl) {
      infoEl.textContent = `Client pays ${fmt_money(effAmt, sym)} — ${fmt_money(discAmt, sym)} discount written off. Invoice will be marked Paid.`;
      infoEl.style.display = 'block';
    }
    if (noteEl) noteEl.textContent = `(after ${fmt_money(discAmt, sym)} settlement discount)`;
    // Auto-fill: update field unless user has manually typed a custom amount
    const amtEl = document.getElementById('paid-amt');
    if (amtEl && amtEl.dataset.userEdited !== 'true') {
      amtEl.value = effAmt.toFixed(2);
      amtEl.dataset.autoValue = amtEl.value;
    }
    // Mark that a discount was applied so it can be reliably undone when cleared
    const discInputEl = document.getElementById('paid-settle-disc');
    if (discInputEl) discInputEl.dataset.wasApplied = 'true';
  }
  updatePaidRemaining();
}

function updatePaidRemaining() {
  const mid  = STATE.activeMenuInvoiceId;
  const inv  = STATE.invoices.find(i=>String(i.id)===mid);
  if (!inv)  return;
  const sym        = inv.currency || '₹';
  const total      = parseFloat(inv.amount || 0);
  const received   = parseFloat(document.getElementById('paid-amt').value) || 0;
  const settleDisc = getSettlementDiscAmt(total);
  const prevPaid   = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === mid)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  // Effective coverage = prevPaid (prior) + received (new cash now) + settleDisc (written off)
  const totalCovered  = prevPaid + received + settleDisc;
  const remaining      = Math.max(0, total - totalCovered);
  const remBox         = document.getElementById('paid-remaining-box');
  if (prevPaid < 0.01 && remaining < 0.01) {
    remBox.style.display = 'none';
  } else {
    remBox.style.display = 'block';
    const el  = id => document.getElementById(id);
    // RECEIVED = already-collected cash (prevPaid) — stable, does not change as user types
    // REMAINING and PCT = live preview using totalCovered (prevPaid + typing + discount)
    const pct = total > 0 ? Math.min(100, Math.round(totalCovered / total * 100)) : 0;
    el('paid-rem-total').textContent    = fmt_money(total, sym);
    el('paid-rem-received').textContent = fmt_money(prevPaid, sym);
    el('paid-rem-due').textContent      = fmt_money(remaining, sym);
    // Breakdown footer — only visible when settlement discount is applied
    const breakdownBox  = document.getElementById('paid-rem-breakdown');
    const breakdownText = document.getElementById('paid-rem-breakdown-text');
    if (settleDisc > 0 && breakdownBox && breakdownText) {
      const parts = [];
      if (prevPaid > 0.001)  parts.push(`${fmt_money(prevPaid, sym)} prev`);
      if (received > 0.001)  parts.push(`${fmt_money(received, sym)} now`);
      parts.push(`${fmt_money(settleDisc, sym)} disc write-off`);
      breakdownText.textContent = ` ${fmt_money(total, sym)} = ` + parts.join(' + ');
      breakdownBox.style.display = 'block';
    } else if (breakdownBox) {
      breakdownBox.style.display = 'none';
    }
    const pctEl = el('paid-rem-pct');
    if (pctEl) pctEl.textContent = pct + '%';
    const bar = el('paid-rem-bar');
    if (bar) bar.style.width = pct + '%';
  }
}

function confirmPaid() {
  const mid = String(STATE.activeMenuInvoiceId);
  const inv = STATE.invoices.find(i=>String(i.id)===mid);
  if (!inv) { closeModal('modal-paid'); return; }

  // ── Validation: split amounts must match ──────────────────────
  const isSplitMethod = document.getElementById('paid-method')?.value === 'Split';
  if (isSplitMethod) {
    const splitRows = document.querySelectorAll('#split-rows .split-amt');
    const splitSum  = Array.from(splitRows).reduce((s,el) => s + (parseFloat(el.value)||0), 0);
    const amtFld    = parseFloat(document.getElementById('paid-amt').value) || 0;
    if (splitSum < 0.01) { toast('⚠️ Enter split amounts for each method', 'warning'); return; }
    if (Math.abs(splitSum - amtFld) > 0.01) {
      toast(`⚠️ Split total (${fmt_money(splitSum,'₹')}) must equal Amount Received (${fmt_money(amtFld,'₹')})`, 'warning');
      return;
    }
  }

  const amtReceived   = parseFloat(document.getElementById('paid-amt').value)||parseFloat(inv.amount)||0;
  const totalAmt      = parseFloat(inv.amount||0);
  const settleDiscAmt = getSettlementDiscAmt(totalAmt);
  const prevPaid      = STATE.payments
    .filter(p => p.invoice_id && String(p.invoice_id) === mid)
    .reduce((s,p) => s + parseFloat(p.amount||0), 0);
  const dueBeforeThis = Math.max(0, totalAmt - prevPaid);

  // ── Validation: amount received cannot exceed what's actually due ──
  if (amtReceived - dueBeforeThis > 0.01) {
    toast(`⚠️ Amount received (${fmt_money(amtReceived,'₹')}) exceeds the amount due (${fmt_money(dueBeforeThis,'₹')}). Please correct the amount.`, 'warning');
    return;
  }

  const totalCovered  = prevPaid + amtReceived + settleDiscAmt;
  const remaining     = Math.max(0, totalAmt - totalCovered);

  // ── Validation: partial checkbox required if amount < total ───
  if (remaining > 0.01) {
    const partialCheckEl = document.getElementById('paid-collect-remaining');
    if (!partialCheckEl || !partialCheckEl.checked) {
      const remBox = document.getElementById('paid-remaining-box');
      if (remBox) { remBox.style.display='block'; remBox.style.border='2px solid #E53935'; remBox.style.background='#FFF3F3'; setTimeout(()=>{ remBox.style.border='1.5px solid #FFD54F'; remBox.style.background='#FFF8E1'; },2500); }
      toast(`⚠️ Amount received (${fmt_money(amtReceived,'₹')}) is less than invoice total (${fmt_money(totalAmt,'₹')}). Please tick "Record as partial" checkbox to keep invoice active for the remaining ${fmt_money(remaining,'₹')}, or enter the full amount.`, 'warning');
      return;
    }
  }

  const isPartial  = remaining > 0.01 && document.getElementById('paid-collect-remaining')?.checked;

  // ── Read wasPartial NOW before modal closes & resets DOM ──────
  const partialCheck = document.getElementById('paid-collect-remaining');
  const wasPartial   = !!(partialCheck && partialCheck.checked && isPartial);

  const payload = {
    invoice_id:          parseInt(mid)||null,
    invoice_number:      inv.num||inv.invoice_number||'',
    client_name:         (STATE.clients.find(c=>String(c.id)===String(inv.client))||{}).name||inv.client_name||'',
    amount:              amtReceived,
    settlement_discount: settleDiscAmt > 0 ? settleDiscAmt : 0,
    payment_date:        (function(){
                            const dVal = document.getElementById('paid-date').value;
                            const tVal = document.getElementById('paid-time').value;
                            if (!dVal) return '';
                            return dVal + ' ' + (tVal || '00:00') + ':00';
                          })(),
    method: (document.getElementById('paid-method').value === 'Split')
              ? getSplitMethodLabel()
              : document.getElementById('paid-method').value,
    transaction_id: document.getElementById('paid-txn').value,
    notes:          document.getElementById('paid-notes')?.value || '',
    status:         'Success',
    partial:        isPartial ? 1 : 0,
    remaining_amt:  isPartial ? remaining : 0,
  };

  // ── Button loading state: disable to prevent double-submit ────
  const confirmBtn = document.getElementById('btn-confirm-paid');
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
  }

  api('api/payments.php','POST',payload)
    .then(() => {
      // ── STEP 1: Close modal & show toast instantly ─────────────
      closeModal('modal-paid');
      if (wasPartial) {
        toast(`✅ Partial payment (${fmt_money(payload.amount,'₹')}) recorded! Remaining: ${fmt_money(payload.remaining_amt,'₹')}.`,'success');
      } else {
        toast('✅ Invoice marked paid & payment recorded!','success');
      }

      // ── STEP 2: WA & Email fire immediately (non-blocking) ─────
      const paidInv = STATE.invoices.find(i => String(i.id) === String(mid)) || inv;
      const cP      = STATE.clients.find(x => String(x.id) === String(paidInv.client)) || {};
      const phoneP  = (cP.wa || cP.whatsapp || cP.phone || paidInv.client_wa || paidInv.client_phone || '').replace(/\D/g,'');
      const waP     = STATE.settings.wa || {};
      const shouldSendWA = wasPartial ? (waP.auto_partial !== '0') : (waP.auto_paid !== '0');
      if (shouldSendWA && phoneP) {
        const isSplitPmt = payload.method && payload.method.startsWith('Split');
        let tplKey, tplDefault, tplName;
        if (wasPartial)       { tplKey=waP.tpl_partial;             tplDefault=getDefaultWATpl('partial_receipt'); tplName='partial_payment'; }
        else if (isSplitPmt)  { tplKey=waP.tpl_split||waP.tpl_paid; tplDefault=getDefaultWATpl('split_receipt');   tplName='split_payment'; }
        else                  { tplKey=waP.tpl_paid;                tplDefault=getDefaultWATpl('paid');            tplName='payment_received'; }
        const tplP = tplKey || tplDefault;
        const invWithPmt = Object.assign({}, paidInv, {
          _paidAmt:      payload.amount,
          _remainingAmt: payload.remaining_amt || 0,
          _payMethod:    payload.method,
          _instalmentNo: STATE.payments.filter(p=>String(p.invoice_id)===mid).length + 1,
          _settleDisc:   payload.settlement_discount || 0,
        });
        const msgP = formatWAMsg(tplP, invWithPmt, cP, STATE.settings);
        logWAMessage({ inv:invWithPmt, client:cP, type:tplName, msg:msgP, status:'sending' });
        sendWA(phoneP, msgP, tplName, invWithPmt, cP)
          .then(r => {
            logWAMessage({ inv:invWithPmt, client:cP, type:tplName, msg:msgP, status:r?'sent_api':'sent_web' });
            const label = wasPartial ? 'Partial payment receipt' : (isSplitPmt ? 'Split payment receipt' : 'Payment receipt');
            toast(`📱 ${label} sent to ${cP.name || phoneP} via WhatsApp!`, 'success');
          })
          .catch(e => {
            logWAMessage({ inv:invWithPmt, client:cP, type:tplName, msg:msgP, status:'failed', error:e.message });
            toast(`⚠️ WhatsApp not sent — ${e.message}`, 'warning');
          });
      } else if (shouldSendWA && !phoneP) {
        toast(`⚠️ WA not sent — no phone number for ${cP.name || 'client'}`, 'warning');
      }

      const shouldSendEmail = wasPartial
        ? (STATE.settings.email_auto_partial !== '0')
        : (STATE.settings.email_auto_paid    !== '0');
      if (shouldSendEmail) {
        const cE     = STATE.clients.find(x => String(x.id) === String(paidInv.client)) || {};
        const emailE = cE.email || paidInv.client_email || '';
        const nameE  = cE.name  || paidInv.client_name  || 'Client';
        if (emailE) {
          api('api/email.php','POST',{ action:'send', type:'receipt', invoice_id:mid, to:emailE, to_name:nameE })
            .then(r => {
              if (r?.success) toast(`📧 Receipt email sent to ${nameE||emailE}!`,'success');
              else            toast('⚠️ Email not sent — check SMTP settings.','warning');
            }).catch(() => toast('⚠️ Email could not be sent.','warning'));
        }
      }

      // ── STEP 3: Reload data silently in background ─────────────
      Promise.all([api('api/invoices.php'), api('api/payments.php')])
        .then(([ir,pr]) => {
          if (ir&&ir.data) { STATE.invoices=ir.data.map(normalizeInvoice); STATE.filteredInvoices=[...STATE.invoices]; }
          if (pr&&pr.data)   STATE.payments=pr.data;
          ['renderInvoicesTable','renderDonutChart','renderDashRecent','renderPayments','updateDashStats','renderDashKpis'].forEach(fn => { if (typeof window[fn] === 'function') window[fn](); });
        })
        .catch(()=>{/* silent — UI already updated */});
    })
    .catch(e => {
      // Re-enable button on failure so user can retry
      if (confirmBtn) { confirmBtn.disabled=false; confirmBtn.innerHTML='<i class="fas fa-check"></i> Confirm Payment'; }
      toast('❌ '+e.message,'error');
    });
}

// ══════════════════════════════════════════
// DELETE
// ══════════════════════════════════════════
// ── Split Payment UI ──────────────────────────────────────
function toggleSplitPayment() {
  const sel    = document.getElementById('paid-method');
  const panel  = document.getElementById('split-payment-panel');
  const amtFld = document.getElementById('paid-amt-field');
  if (!panel) return;
  const isSplit = sel?.value === 'Split';
  panel.style.display = isSplit ? 'block' : 'none';
  if (amtFld) amtFld.style.opacity = isSplit ? '0.6' : '1';
  if (isSplit) {
    // Pre-fill first row with full amount received, second row 0 — user adjusts
    const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
    const rows = document.querySelectorAll('#split-rows .split-amt');
    if (rows.length >= 2) {
      rows[0].value = totalAmt > 0 ? totalAmt.toFixed(2) : '';
      rows[1].value = '';
    }
    updateSplitTotal();
    // Show partial info box if amount < invoice total
    updatePaidRemaining();
  }
}

function updateSplitTotal() {
  const rows = document.querySelectorAll('#split-rows .split-amt');
  const amts = Array.from(rows).map(el => parseFloat(el.value)||0);
  const splitSum = amts.reduce((s,v) => s+v, 0);
  const el = document.getElementById('split-total');
  if (el) el.textContent = fmt_money(splitSum, '₹');

  // Auto-fill last row with remainder when first row changes
  // Only when exactly 2 rows and user typed in row 0
  const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
  if (rows.length === 2 && totalAmt > 0) {
    // Identify which row triggered the input — the one with focus
    const focusedRow = document.activeElement?.closest('.split-row');
    const focusedIdx = focusedRow ? Array.from(document.querySelectorAll('#split-rows .split-row')).indexOf(focusedRow) : -1;
    if (focusedIdx === 0) {
      const remainder = Math.max(0, totalAmt - (parseFloat(rows[0].value)||0));
      rows[1].value = remainder > 0 ? remainder.toFixed(2) : '';
    } else if (focusedIdx === 1) {
      const remainder = Math.max(0, totalAmt - (parseFloat(rows[1].value)||0));
      rows[0].value = remainder > 0 ? remainder.toFixed(2) : '';
    }
  }

  // Re-calc split sum after auto-fill
  const finalAmts = Array.from(document.querySelectorAll('#split-rows .split-amt')).map(el => parseFloat(el.value)||0);
  const finalSum = finalAmts.reduce((s,v) => s+v, 0);
  if (el) el.textContent = fmt_money(finalSum, '₹');

  // Show mismatch warning if split total differs from Amount Received
  const amtReceived = parseFloat(document.getElementById('paid-amt')?.value) || 0;
  const warnEl = document.getElementById('split-mismatch-warn');
  if (warnEl) {
    if (amtReceived > 0 && Math.abs(finalSum - amtReceived) > 0.01) {
      warnEl.style.display = 'block';
      warnEl.textContent = finalSum > amtReceived
        ? `⚠️ Split total (${fmt_money(finalSum,'₹')}) exceeds Amount Received`
        : `⚠️ Split total (${fmt_money(finalSum,'₹')}) is less than Amount Received`;
    } else {
      warnEl.style.display = 'none';
    }
  }

  // Update split breakdown bar
  renderSplitBreakdown();

  // Keep partial info box in sync
  updatePaidRemaining();
}

function renderSplitBreakdown() {
  const barEl = document.getElementById('split-breakdown-bar');
  if (!barEl) return;
  const totalAmt = parseFloat(document.getElementById('paid-amt')?.value) || 0;
  const rows = document.querySelectorAll('#split-rows .split-row');
  const parts = Array.from(rows).map((row, i) => {
    const method = row.querySelector('.split-method')?.value || '';
    const amt    = parseFloat(row.querySelector('.split-amt')?.value) || 0;
    const shortM = method.split(' ')[0]; // UPI, Bank, Cash, Cheque, Credit
    const colors = ['#1565C0','#2E7D32','#E65100','#6A1B9A','#B71C1C'];
    const col = colors[i % colors.length];
    return `<span style="display:inline-flex;align-items:center;gap:4px">
      <span style="font-weight:700;color:${col}">${shortM}:</span>
      <span style="font-family:var(--mono);color:${col}">${fmt_money(amt,'₹')}</span>
    </span>`;
  });
  barEl.innerHTML = `
    <span style="display:inline-flex;align-items:center;gap:4px">
      <span style="font-weight:700;color:var(--teal)">Total:</span>
      <span style="font-family:var(--mono);color:var(--teal)">${fmt_money(totalAmt,'₹')}</span>
    </span>
    <span style="color:var(--muted2)">|</span>
    ${parts.join('<span style="color:var(--muted2)">|</span>')}`;
  barEl.style.display = rows.length > 0 ? 'flex' : 'none';
}

function addSplitRow() {
  const container = document.getElementById('split-rows');
  const row = document.createElement('div');
  row.className = 'split-row';
  row.style.cssText = 'display:flex;gap:8px;align-items:center';
  row.innerHTML = `<select class="split-method" style="flex:1;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" onchange="renderSplitBreakdown()">
    <option>UPI (GPay/PhonePe/Paytm)</option>
    <option>Bank Transfer (NEFT/RTGS)</option>
    <option>Cash</option><option>Cheque</option><option>Credit Card</option>
  </select>
  <input type="number" class="split-amt" placeholder="0.00" style="width:100px;padding:7px 8px;border-radius:8px;border:1px solid var(--border);font-size:12px" oninput="updateSplitTotal()">
  <button onclick="removeSplitRow(this)" style="padding:6px 10px;background:#FFEBEE;color:#C62828;border:none;border-radius:7px;cursor:pointer;font-size:12px">✕</button>`;
  container.appendChild(row);
  renderSplitBreakdown();
}

function removeSplitRow(btn) {
  const rows = document.querySelectorAll('#split-rows .split-row');
  if (rows.length <= 2) { toast('⚠️ Keep at least 2 split methods', 'warning'); return; }
  btn.closest('.split-row').remove();
  updateSplitTotal();
  renderSplitBreakdown();
}

function getSplitMethodLabel() {
  const rows = document.querySelectorAll('#split-rows .split-row');
  const parts = Array.from(rows).map(r => {
    const m = r.querySelector('.split-method')?.value || '';
    const a = parseFloat(r.querySelector('.split-amt')?.value || 0);
    return a > 0 ? `${m.split(' ')[0]}: ₹${a.toFixed(0)}` : null;
  }).filter(Boolean);
  return 'Split: ' + parts.join(' + ');
}

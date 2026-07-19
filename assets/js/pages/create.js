// ============================================================
// create.js — page-specific JS for pages/create.php (New/Edit Invoice)
// Depends on: common.js, shared-data.js, wa-shared.js,
// invoice-render-shared.js
//
// EDIT MODE: invoices.js's editInvoice() redirects here with
// ?edit_id=X instead of the SPA's STATE._editingNext flag + showPage().
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['invoices', 'clients', 'products', 'settings']);
  addItem();
  updateClientDropdown();

  const params = new URLSearchParams(window.location.search);
  const editId = params.get('edit_id');
  if (editId) {
    const inv = STATE.invoices.find(i => String(i.id) === String(editId));
    if (inv) {
      STATE.editingInvoiceId = editId;
      loadInvoiceIntoForm(inv);
    } else {
      toast('❌ Invoice not found', 'error');
    }
  }

  // Arrived from clients.js's "Create Invoice" link (createInvoiceForClient)
  const clientId = params.get('client');
  if (clientId) {
    const sel = document.getElementById('f-client-select');
    if (sel) { sel.value = clientId; fillClientForm(clientId); }
  }

  // Arrived from products.php's "Add to Invoice" button
  const addProductId = params.get('addProduct');
  if (addProductId) {
    pickProduct(addProductId);
  }
});

function addItem() {
  const fgst = document.getElementById('f-gst');
  const gstVal = fgst ? fgst.value : String(STATE.settings.defaultGST ?? 18);
  const defaultGst = (gstVal !== '' && gstVal !== null) ? parseInt(gstVal) : (STATE.settings.defaultGST ?? 18);
  formItems.push({ id: Date.now(), desc: '', itemType: 'Service', qty: 1, gst: defaultGst, rate: 0 });
  renderFormItems();
}

function calcTotals() {
  // Per-item GST calculation
  let sub = 0, gstAmt = 0;
  formItems.forEach(item => {
    const lineAmt = (item.qty||1)*(item.rate||0);
    sub += lineAmt;
    const gstRate = parseFloat(item.gst ?? 0);
    gstAmt += lineAmt * gstRate / 100;
  });
  const disc    = parseFloat(document.getElementById('f-disc')?.value) || 0;
  const discType = document.getElementById('f-disc-type')?.value || 'pct';
  const discAmt = discType === 'fixed' ? Math.min(disc, sub) : sub * disc / 100;
  const discPct = sub > 0 ? (discAmt / sub * 100) : 0;
  // Recalculate GST after discount proportionally
  const discFactor = sub > 0 ? (1 - discAmt/sub) : 1;
  const gstAfterDisc = gstAmt * discFactor;
  const grand = sub - discAmt + gstAfterDisc;

  const set = (id, val) => { const e = document.getElementById(id); if(e) e.textContent = val; };
  set('tp-sub',    fmt_money(sub));
  set('tp-disc',   '-'+fmt_money(discAmt)+(discType==='fixed'?' (₹ fixed)':disc>0?' ('+disc+'%)':''));
  set('tp-amount', fmt_money(sub - discAmt));
  set('tp-gst',    '+'+fmt_money(gstAfterDisc));
  // Show GST breakdown per item
  const bd = document.getElementById('tp-gst-breakdown');
  if (bd) {
    const rates = [...new Set(formItems.filter(i=>parseFloat(i.gst??0)>0).map(i=>parseFloat(i.gst??0)))];
    if (rates.length <= 1) {
      bd.textContent = rates.length ? rates[0]+'% on subtotal' : '';
    } else {
      bd.textContent = formItems.filter(i=>parseFloat(i.gst??0)>0)
        .map(i => { const b=(i.qty||1)*(i.rate||0); return parseFloat(i.gst)+'% on '+fmt_money(b); })
        .join(' + ');
    }
  }
  set('tp-grand', fmt_money(grand));

  // Update the global GST selector display (show blended or first item rate)
  livePreview();
  return { sub, discAmt, gstAmt: gstAfterDisc, grand };
}

async function cancelInvoiceForm() {
  const isEditing = !!STATE.editingInvoiceId;
  const { isConfirmed } = await Swal.fire({
    title: isEditing ? 'Discard Changes?' : 'Discard Invoice?',
    text: isEditing ? 'Your unsaved changes will be lost.' : 'This draft will not be saved.',
    icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Yes, Discard', cancelButtonText: 'Keep Editing',
    confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' }
  });
  if (!isConfirmed) return;
  STATE.editingInvoiceId = null;
  STATE._editingNext     = false;
  window.location.href = '/pages/invoices.php';
}

function fillClientForm(val) {
  const notice  = document.getElementById('onetime-notice');
  const badge   = document.getElementById('onetime-badge');
  if (val === '__onetime__') {
    // Clear all client fields for manual entry
    ['f-cname','f-cperson','f-cwa','f-cemail','f-cgst','f-caddr'].forEach(id => {
      const e = document.getElementById(id); if (e) e.value = '';
    });
    if (notice) notice.style.display = 'block';
    if (badge)  badge.style.display  = 'inline-flex';
    document.getElementById('f-cname')?.focus();
    livePreview();
    return;
  }
  // Hide one-time indicators when a saved client is selected or cleared
  if (notice) notice.style.display = 'none';
  if (badge)  badge.style.display  = 'none';
  const c = STATE.clients.find(x => x.id === val);
  if (!c) return;
  document.getElementById('f-cname').value   = c.name;
  document.getElementById('f-cperson').value = c.person;
  document.getElementById('f-cwa').value     = c.wa;
  document.getElementById('f-cemail').value  = c.email;
  document.getElementById('f-cgst').value    = c.gst;
  document.getElementById('f-caddr').value   = c.addr;
  livePreview();
}

async function handleLogoUpload(input, targetId, previewId) {
  const file = input.files[0]; if (!file) return;
  if (file.size > 3*1024*1024) { toast('⚠️ Max 3MB', 'warning'); return; }
  const typeMap = {
    'f-company-logo':'logo','sc-logo':'logo',
    'f-signature':'signature','sc-sign':'signature',
    'f-client-logo':'client_logo','f-qr':'qr'
  };
  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', typeMap[targetId] || 'logo');
  try {
    const res  = await fetch('api/upload.php', { method:'POST', body:fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('Upload failed: server returned HTML'); }
    if (!data.success) throw new Error(data.error || 'Upload failed');
    const el = document.getElementById(targetId);
    if (el) { el.value = data.url; el.dispatchEvent(new Event('input')); }
    if (targetId === 'sc-logo' || targetId === 'f-company-logo') {
      STATE.settings.logo = data.url;
    } else if (targetId === 'sc-sign' || targetId === 'f-signature') {
      STATE.settings.signature = data.url;
    }
    // Set the hidden input value so getFormData picks it up
    const _tgtInput = document.getElementById(targetId);
    if (_tgtInput && _tgtInput.tagName === 'INPUT') _tgtInput.value = data.url;
    if (previewId) {
      const prev = document.getElementById(previewId);
      if (prev) {
        const isSign = previewId.includes('sign');
        prev.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:${isSign?'#1a1a2e':'var(--teal-bg)'};border-radius:8px;border:1px solid var(--border)">
          <img src="${data.url}" style="height:${isSign?'36':'32'}px;max-width:120px;object-fit:contain;border-radius:4px">
          <span style="font-size:11px;color:var(--muted)">${file.name}</span>
          <button onclick="clearLogoField('${targetId}','${previewId}')" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:13px"><i class="fas fa-times"></i></button>
        </div>`;
      }
    }
    toast('✅ Uploaded!', 'success');
  } catch(e) {
    // Fallback: use base64
    const reader = new FileReader();
    reader.onload = ev => {
      const el = document.getElementById(targetId);
      if (el) { el.value = ev.target.result; el.dispatchEvent(new Event('input')); }
      toast('✅ Image loaded', 'success');
    };
    reader.readAsDataURL(file);
    console.warn('Server upload failed, using base64:', e.message);
  }
}

function livePreview() {
  const wrap = document.getElementById('invoicePreviewWrap');
  if (!wrap) return;
  try {
    const d = getFormData();
    const scale = 0.685;
    const html = buildInvoiceHTML(d, false);
    if (!html || html.trim() === '') {
      wrap.innerHTML = `<div style="padding:20px;color:#e53935;font-size:12px">Preview returned empty — template may not be loading correctly. Check console for errors.</div>`;
      return;
    }
    // Lay the content out unscaled first so we can measure its true height —
    // a fixed one-A4-page height here would clip invoices with enough items,
    // notes, or terms to run longer than a single page.
    wrap.style.cssText = `width:545px;position:relative;border-radius:6px;box-shadow:0 2px 16px rgba(0,0,0,.12);background:#fff;overflow:hidden`;
    const inner = document.createElement('div');
    inner.style.cssText = `width:794px;position:absolute;top:0;left:0;pointer-events:none`;
    inner.innerHTML = html;
    wrap.innerHTML = '';
    wrap.appendChild(inner);
    const naturalH = Math.max(1123, inner.scrollHeight);
    inner.style.transform = `scale(${scale})`;
    inner.style.transformOrigin = 'top left';
    wrap.style.height = Math.round(naturalH * scale) + 'px';
    // Sync template dropdowns
    const ps = document.getElementById('prevTplSelect');
    if (ps && ps.value !== String(d.tpl)) ps.value = d.tpl;
  } catch(e) {
    console.error('livePreview error:', e);
    const wrap2 = document.getElementById('invoicePreviewWrap');
    if (wrap2) wrap2.innerHTML = `<div style="padding:20px;color:#e53935;font-size:12px">Preview error: ${e.message}<br><small style="color:#aaa">${e.stack?.split('\n')[1]||''}</small></div>`;
  }
}

function markFormPaid() { openPaidModal(null); }

function onServiceSelect(val) {
  if (!val) return;
  const customInp = document.getElementById('f-service-custom');
  if (customInp) customInp.value = val;
}

function onStatusChange(newStatus) {
    const numEl = document.getElementById('f-num');
    if (!numEl) return;

    const estPfx = STATE.settings.estPrefix || ('QT-' + new Date().getFullYear() + '-');
    const invPfx = STATE.settings.prefix    || ('OT-' + new Date().getFullYear() + '-');

    if (newStatus === 'Estimate') {
        // If editing a Draft, rename its INV- number to QT- prefix (keep sequence)
        if (STATE.editingInvoiceId && STATE._editOrigStatus === 'Draft') {
            const current = numEl.value || '';
            const seqMatch = current.match(/(\d+)$/);
            const seq = seqMatch ? seqMatch[1] : '001';
            numEl.value = estPfx + seq.padStart(3, '0');
            return;
        }
        // New estimate — auto-generate next QT number
        let nextSeq = 1;
        STATE.invoices.forEach(inv => {
            const n = inv.num || inv.invoice_number || '';
            if (n.startsWith(estPfx)) {
                const seq = parseInt(n.slice(estPfx.length), 10);
                if (!isNaN(seq) && seq >= nextSeq) nextSeq = seq + 1;
            }
        });
        if (nextSeq === 1) {
            const estCount = STATE.invoices.filter(i => i.status === 'Estimate').length;
            if (estCount > 0) nextSeq = estCount + 1;
        }
        numEl.value = estPfx + String(nextSeq).padStart(3, '0');
        return;
    }

    // Switching back to Invoice from Estimate: regenerate invoice number
    const current = numEl.value || '';
    if (current.startsWith(estPfx)) {
        let nextInvSeq = 1;
        STATE.invoices.forEach(inv => {
            const n = inv.num || inv.invoice_number || '';
            if (n.startsWith(invPfx)) {
                const seq = parseInt(n.slice(invPfx.length), 10);
                if (!isNaN(seq) && seq >= nextInvSeq) nextInvSeq = seq + 1;
            }
        });
        if (nextInvSeq === 1 && STATE.invoices.length > 0) nextInvSeq = STATE.invoices.length + 1;
        numEl.value = invPfx + String(nextInvSeq).padStart(3, '0');
    }
}

function openProductPicker() {
  const list = document.getElementById('productPickerList');
  if (!STATE.products.length) {
    list.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted)"><i class="fas fa-box-open" style="font-size:28px;display:block;margin-bottom:8px;opacity:.3"></i>No services yet. Add from Services/Products page.</div>';
  } else {
    list.innerHTML = STATE.products.map(p => `<div class="pp-item" onclick="pickProduct('${p.id}')">
      <div><div class="pp-name">${escHtml(p.name)}</div><div style="font-size:11px;color:var(--muted)">${escHtml(p.category)} · GST:${p.gst}%</div></div>
      <div class="pp-rate">${fmt_money(p.rate)}</div>
    </div>`).join('');
  }
  openModal('modal-products');
}

function pickProduct(id) {
  const p = STATE.products.find(x=>x.id===id);
  if (!p) return;
  const gst = (p.gst !== undefined && p.gst !== null && p.gst !== '') ? parseFloat(p.gst) : 18;
  // If there is exactly one empty default row, fill it instead of adding a new row
  if (formItems.length === 1 && !formItems[0].desc && !formItems[0].rate) {
    formItems[0].desc     = p.name;
    formItems[0].itemType = p.category || 'Service';
    formItems[0].qty      = 1;
    formItems[0].gst      = gst;
    formItems[0].rate     = p.rate;
  } else {
    formItems.push({ id: Date.now(), desc: p.name, itemType: p.category || 'Service', qty: 1, gst, rate: p.rate });
  }
  renderFormItems();
  livePreview();
  closeModal('modal-products');
  toast(`✅ "${p.name}" added`, 'success');
}

function printCurrentInvoice() {
  printInvoiceData({ items: formItems });
}

async function saveInvoice() {
  const d = getFormData();
  if (!d.cname || d.cname === 'Client Name') { toast('⚠️ Please enter client name', 'warning'); return; }
  if (formItems.length === 0) { toast('⚠️ Add at least one line item', 'warning'); return; }
  const selVal = document.getElementById('f-client-select')?.value;
  const _clientId = (selVal && selVal !== '__onetime__') ? parseInt(selVal) : null;

  // FIX: capture BEFORE any reset — tells WA block if this is new vs edit
  const isNewSave = !STATE.editingInvoiceId;
  // FIX: capture phone from form NOW before page navigates away and resets form
  const formPhone = (document.getElementById('f-cwa')?.value || '').replace(/\D/g, '');

  const payload = {
    invoice_number: d.num, client_id: _clientId,
    client_name: d.cname, service_type: d.svc, issued_date: d.date, due_date: d.due,
    status: d.status, currency: d.sym, subtotal: d.sub,
    discount_pct: d.disc, discount_amt: d.discAmt, discount_type: (d.discType==='fixed'?'flat':'percent'), gst_amount: d.gstAmt, grand_total: d.grand,
    notes: d.notes || '', bank_details: d.bank || '', terms: d.tnc || '',
    company_logo: d.companyLogo, client_logo: d.clientLogo,
    signature: d.signature, qr_code: d.qrUrl,
    template_id: d.tpl, generated_by: d.generatedBy, show_generated: d.showGeneratedBy ? 1 : 0,
    pdf_options: d.popt,
    // One-time client fields — stored on invoice row so they survive edit/reload
    client_person: d.cperson || '',
    client_wa:     d.cwa     || '',
    client_email:  d.cemail  || '',
    client_gst:    d.cgst    || '',
    client_addr:   d.caddr   || '',
    items: formItems.map(i => ({ desc: i.desc, itemType: i.itemType||'Service', qty: parseFloat(i.qty)||1, rate: parseFloat(i.rate)||0, gst: (i.gst !== undefined && i.gst !== null && i.gst !== '') ? parseFloat(i.gst) : 18 }))
  };
  try {
    if (!isNewSave) {
      const inv = STATE.invoices.find(i => String(i.id) === String(STATE.editingInvoiceId));
      const dbId = inv?._dbId || parseInt(inv?.id) || 0;
      const _wasDraft = STATE._editOrigStatus === 'Draft';
      await api('/api/invoices.php?id=' + dbId, 'PUT', payload);
      toast('✅ Invoice updated!', 'success');
      const _editedInv = inv || {};
      const _editedNum = _editedInv.num || _editedInv.invoice_number || payload.invoice_number || '';
      if (payload.status === 'Estimate') {
        logActivity('estimate_edited', `Estimate edited: ${_editedNum}`, payload.client_name || '', dbId);
      } else {
        logActivity('invoice_edited', `Invoice edited: ${_editedNum}`, payload.client_name || '', dbId);
      }
      // Navigate back to invoices list after editing
      window.location.href = '/pages/invoices.php';
      // ── Draft→Estimate promotion: offer to send to client ────────────
      if (_wasDraft && payload.status === 'Estimate') {
        setTimeout(() => {
          const _estInv = STATE.invoices.find(i => String(i.id) === String(dbId)) || inv || {};
          const _estClient = STATE.clients.find(x => String(x.id) === String(_estInv.client || _estInv.client_id || selVal)) || {};
          const _estPhone = (_estClient.wa || _estClient.whatsapp || _estClient.phone || formPhone || '').replace(/\D/g, '');
          const _estEmail = _estClient.email || '';
          Swal.fire({
            icon:              'success',
            title:             `Estimate ${payload.invoice_number} saved!`,
            html:              `<p style="font-size:14px;margin:8px 0">Draft promoted to Estimate.<br>Send to <strong>${payload.client_name || 'client'}</strong> now?</p>`,
            showDenyButton:    !!_estEmail,
            showCancelButton:  true,
            confirmButtonText: '📱 WhatsApp',
            denyButtonText:    '📧 Email',
            cancelButtonText:  'Later',
            confirmButtonColor:'#25D366',
            denyButtonColor:   '#1976D2',
            customClass:       { popup: 'swal-compact' },
          }).then(r => {
            if (r.isConfirmed && _estPhone) {
              const wa = STATE.settings.wa || {};
              const tpl = wa.tpl_estimate || getDefaultWATpl('estimate');
              const msg = formatWAMsg(tpl, _estInv, _estClient, STATE.settings);
              logWAMessage({ inv: _estInv, client: _estClient, type: 'estimate_created', msg, status: 'sending' });
              sendWA(_estPhone, msg, 'estimate_created', _estInv, _estClient)
                .then(res => {
                  logWAMessage({ inv: _estInv, client: _estClient, type: 'estimate_created', msg, status: res ? 'sent_api' : 'sent_web' });
                  toast(`📱 Estimate sent to ${_estClient.name || _estPhone} via WhatsApp!`, 'success');
                })
                .catch(e => toast(`⚠️ WhatsApp not sent — ${e.message}`, 'warning'));
            } else if (r.isConfirmed && !_estPhone) {
              toast(`⚠️ No phone number for ${payload.client_name || 'client'}`, 'warning');
            } else if (r.isDenied && _estEmail) {
              const ec = STATE.settings.email_cfg || STATE.settings || {};
              if (ec.smtp_host || ec.smtp_user) {
                api('/api/email.php', 'POST', { action: 'send', type: 'estimate', invoice_id: dbId, to: _estEmail, to_name: payload.client_name || '' })
                  .then(res => {
                    if (res?.success) toast(`📧 Estimate email sent to ${payload.client_name || _estEmail}!`, 'success');
                    else toast(`⚠️ Email not sent — check SMTP settings.`, 'warning');
                  }).catch(() => toast(`⚠️ Email could not be sent.`, 'warning'));
              } else {
                toast(`⚠️ SMTP not configured — go to Settings > Email`, 'warning');
              }
            }
          });
        }, 600);
      }
      STATE._editOrigStatus = null;
    } else {
      const _res = await api('/api/invoices.php', 'POST', payload);
      if (payload.status === 'Draft') {
        // Draft save — show actionable toast with "Send to Client" button
        const _draftNum = d.num;
        const _draftId  = null; // id resolved after reload
        toast('📝 Saved as Draft — remember to send to client when ready', 'info');
        // Show a Swal nudge after short delay so user can act
        setTimeout(() => {
          const _savedDraftInv = STATE.invoices.find(i => (i.num||i.invoice_number) === _draftNum);
          if (!_savedDraftInv) return;
          Swal.fire({
            toast:             true,
            position:          'bottom-end',
            icon:              'info',
            title:             `Draft ${_draftNum} not sent`,
            html:              `<span style='font-size:13px'>Ready to send to client?</span>`,
            showCancelButton:  true,
            confirmButtonText: '📤 Make Pending',
            cancelButtonText:  'Later',
            confirmButtonColor:'#00897B',
            timer:             8000,
            timerProgressBar:  true,
            customClass:       { popup: 'swal-compact' },
          }).then(r => {
            if (r.isConfirmed) changeInvoiceStatus(_savedDraftInv.id, 'Pending');
          });
        }, 1200);
      } else {
        toast('✅ Invoice ' + d.num + ' saved!', 'success');
      }
      if (payload.status === 'Estimate') {
        logActivity('estimate_created', `Estimate created: ${d.num}`, payload.client_name || '');
      } else {
        logActivity('invoice_created', `Invoice created: ${d.num}`, payload.client_name || '');
      }
      // Navigate to invoices list — showPage will trigger resetCreateForm next time 'create' is opened
      window.location.href = '/pages/invoices.php';
    }
    const r = await api('/api/invoices.php');
    STATE.invoices = Array.isArray(r.data) ? r.data.map(normalizeInvoice) : [];
    STATE.filteredInvoices = [...STATE.invoices];
    STATE.editingInvoiceId = null;
    renderInvoicesTable(); renderDashRecent(); renderDonutChart(); updateDashStats();
    const badge = document.getElementById('badge-invoices');
    if (badge) badge.textContent = STATE.invoices.filter(i => !['Cancelled','Estimate'].includes(i.status)).length;
    // Auto-generate portal link for new invoices (silent background)
    if (d.id || d.invoice_id) {
      const portalInvId = parseInt(d.id || d.invoice_id);
      if (portalInvId) {
        api('/api/portal.php', 'POST', { invoice_id: portalInvId })
          .then(res => { if (res && res.token) _portalTokenCache[String(portalInvId)] = res.token; })
          .catch(() => {});
      }
    } else {
      const savedInv = STATE.invoices.find(i =>
        (i.num && d.num && i.num === d.num) ||
        (i.invoice_number && d.invoice_number && i.invoice_number === d.invoice_number)
      );
      if (savedInv && savedInv.id) {
        const _sid = String(savedInv.id);
        // FIX: only generate token if not already cached — prevents token replacement
        if (!_portalTokenCache[_sid]) {
          api('/api/portal.php', 'POST', { invoice_id: parseInt(savedInv.id) })
            .then(res => { if (res && res.token) _portalTokenCache[_sid] = res.token; })
            .catch(() => {});
        }
      }
    }

    // ── Auto-send WA: only on NEW save, never on edit ──────────────────
    if (!isNewSave) return; // FIX: skip WA entirely for edits

    const wa = STATE.settings.wa || {};

    // FIX: robust lookup — match by .num or .invoice_number
    const saved = STATE.invoices.find(i =>
      (i.num && i.num === d.num) || (i.invoice_number && i.invoice_number === d.num)
    );
    const savedStatus = saved?.status || d.status || '';

    // FIX: helper that resolves phone from client record + form field fallback
    const resolvePhone = (inv) => {
      const c = STATE.clients.find(x => String(x.id) === String(inv?.client || inv?.client_id || selVal)) || {};
      return { c, phone: (c.wa || c.whatsapp || c.phone || formPhone || '').replace(/\D/g, '') };
    };

    if (savedStatus === 'Draft') {
      // Never send WA for drafts

    } else if (savedStatus === 'Estimate') {
      // FIX: fire even if `saved` is undefined — use form data as fallback
      if (wa.auto_estimate === '1') {
        const invForWA = saved || { num: d.num, client: selVal, client_id: selVal, client_name: d.cname, amount: d.grand, due: d.due, service: d.svc, status: 'Estimate' };
        const { c, phone } = resolvePhone(invForWA);
        if (phone) {
          const tpl = wa.tpl_estimate || getDefaultWATpl('estimate');
          const msg = formatWAMsg(tpl, invForWA, c, STATE.settings);
          logWAMessage({ inv: invForWA, client: c, type: 'estimate_created', msg, status: 'sending' });
          sendWA(phone, msg, 'estimate_created', invForWA, c)
            .then(res => {
              logWAMessage({ inv: invForWA, client: c, type: 'estimate_created', msg, status: res ? 'sent_api' : 'sent_web' });
              toast(`📱 Estimate sent to ${c.name || phone} via WhatsApp!`, 'success');
            })
            .catch(e => {
              logWAMessage({ inv: invForWA, client: c, type: 'estimate_created', msg, status: 'failed', error: e.message });
              toast(`⚠️ WhatsApp not sent — ${e.message}`, 'warning');
            });
        } else {
          toast(`⚠️ WA not sent — no phone number for ${d.cname || 'client'}`, 'warning');
        }
      }

    } else {
      // Normal invoice WA for Pending / Paid / Overdue etc.
      if (wa.auto_inv === '1') {
        const invForWA = saved || { num: d.num, client: selVal, client_id: selVal, client_name: d.cname, amount: d.grand, due: d.due, service: d.svc, status: d.status };
        const { c, phone } = resolvePhone(invForWA);
        if (phone) {
          const tpl = wa.tpl_inv || getDefaultWATpl('inv');
          const msg = formatWAMsg(tpl, invForWA, c, STATE.settings);
          logWAMessage({ inv: invForWA, client: c, type: 'invoice_created', msg, status: 'sending' });
          sendWA(phone, msg, 'invoice_created', invForWA, c)
            .then(res => {
              logWAMessage({ inv: invForWA, client: c, type: 'invoice_created', msg, status: res ? 'sent_api' : 'sent_web' });
              toast(`📱 Invoice sent to ${c.name || phone} via WhatsApp!`, 'success');
            })
            .catch(e => {
              logWAMessage({ inv: invForWA, client: c, type: 'invoice_created', msg, status: 'failed', error: e.message });
              toast(`⚠️ WhatsApp not sent — ${e.message}`, 'warning');
            });
        } else {
          toast(`⚠️ WA not sent — no phone number for ${d.cname || 'client'}`, 'warning');
        }
      }
    }

    // ── Auto-send Email: only on NEW save, respects email_auto_inv / email_auto_est ──
    const ec = STATE.settings.email_cfg || STATE.settings || {};
    const clientForEmail = STATE.clients.find(x => String(x.id) === String(selVal)) || {};
    const emailAddr  = clientForEmail.email || '';
    const clientName = clientForEmail.name  || d.cname || '';
    const shouldEmailInv = savedStatus !== 'Draft' && savedStatus !== 'Estimate' && ec.email_auto_inv === '1';
    const shouldEmailEst = savedStatus === 'Estimate' && ec.email_auto_est === '1';
    if ((shouldEmailInv || shouldEmailEst) && emailAddr && (ec.smtp_host || ec.smtp_user)) {
      const emailInvId = saved?.id || STATE.invoices.find(i => i.num && i.num === d.num)?.id;
      if (emailInvId) {
        api('/api/email.php', 'POST', {
          action:     'send',
          type:       savedStatus === 'Estimate' ? 'estimate' : 'invoice',
          invoice_id: emailInvId,
          to:         emailAddr,
          to_name:    clientName,
        }).then(r => {
          if (r?.success) toast(`📧 ${savedStatus === 'Estimate' ? 'Estimate' : 'Invoice'} email sent to ${clientName || emailAddr}!`, 'success');
          else            toast(`⚠️ Email not sent — check SMTP settings.`, 'warning');
        }).catch(() => toast(`⚠️ Email could not be sent.`, 'warning'));
      }
    } else if ((shouldEmailInv || shouldEmailEst) && !emailAddr) {
      toast(`⚠️ Email not sent — no email address for ${clientName || 'client'}`, 'warning');
    }

  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function savePoptPrefs() {
  const prefs = {};
  POPT_IDS.forEach(id => {
    const el = document.getElementById(id);
    if (el) prefs[id] = el.checked;
  });
  try { localStorage.setItem(POPT_STORAGE_KEY, JSON.stringify(prefs)); } catch(e) {}
}

function sendEmailFromForm() {
  const email = document.getElementById('f-cemail').value;
  const name  = document.getElementById('f-cname').value;
  const num   = document.getElementById('f-num').value;
  const d     = getFormData();
  sendEmailForClient(email, name, num, fmt_money(d.grand, d.sym), d.due, d.svc, d);
}

function sendWAFromForm() {
  const wa   = document.getElementById('f-cwa').value;
  const name = document.getElementById('f-cname').value;
  const num  = document.getElementById('f-num').value;
  const d    = getFormData();
  sendWAMessage(wa, name, num, fmt_money(d.grand, d.sym), d.due);
}

function switchToSaveClient() {
  // Pre-fill the Add Client modal with values already typed in the invoice form
  const get = id => document.getElementById(id)?.value || '';
  const nc = {
    'nc-name':     get('f-cname'),
    'nc-person':   get('f-cperson'),
    'nc-wa':       get('f-cwa'),
    'nc-email':    get('f-cemail'),
    'nc-gst':      get('f-cgst'),
    'nc-addr':     get('f-caddr'),
  };
  Object.entries(nc).forEach(([id, val]) => {
    const e = document.getElementById(id); if (e) e.value = val;
  });
  // Reset one-time mode
  const s = document.getElementById('f-client-select');
  if (s) s.value = '';
  const notice = document.getElementById('onetime-notice');
  const badge  = document.getElementById('onetime-badge');
  if (notice) notice.style.display = 'none';
  if (badge)  badge.style.display  = 'none';
  clearClientLogo();
  updateClientLogoInitials();
  _ncCurrentTags = []; _renderTagPills();
  const _ecwReset2 = document.getElementById('nc-extra-contacts'); if(_ecwReset2) _ecwReset2.innerHTML = '';
  openModal('modal-addclient');
}

function syncServiceText(val) {
  // Keep select in sync when user types manually
  const sel = document.getElementById('f-service');
  if (!sel) return;
  const match = Array.from(sel.options).find(o => o.value === val);
  sel.value = match ? val : '';
}

function syncThemePicker() {
  const tplId   = String(document.getElementById('f-template')?.value || STATE.settings.activeTemplate || '2');
  const picker  = document.getElementById('tpl2-theme-picker');
  const cpicker = document.getElementById('tpl-color-pickers');
  const isTpl2  = tplId === '2';
  const isTplF  = tplId === 'F';
  if (picker)  picker.style.display  = isTpl2 ? 'block' : 'none';
  if (cpicker) cpicker.style.display = (isTpl2 || isTplF) ? 'none' : 'grid';
  // Persist selected template so it survives page refresh
  if (STATE.settings.activeTemplate !== tplId) {
    STATE.settings.activeTemplate = tplId;
    // Sync sd-tpl and other selects
    const sdTpl = document.getElementById('sd-tpl');
    if (sdTpl) sdTpl.value = tplId;
    const prevSel = document.getElementById('prevTplSelect');
    if (prevSel) prevSel.value = tplId;
    // Save to DB silently
    api('/api/settings.php', 'POST', { active_template: tplId }).catch(() => {});
  }
}

function updateDueFromIssue() {
  const dateEl = document.getElementById('f-date');
  const dueEl  = document.getElementById('f-due');
  if (!dateEl || !dueEl) return;
  const issueDate = new Date(dateEl.value);
  if (isNaN(issueDate)) return;
  const dueDays = parseInt(STATE.settings.dueDays) || 15;
  const due = new Date(issueDate);
  due.setDate(issueDate.getDate() + dueDays);
  dueEl.value = fmt_date(due);
}

function getFormData() {
  const tpl     = document.getElementById('f-template')?.value || STATE.settings.activeTemplate || '2';
  // FIX: never send blank invoice_number — auto-generate from prefix if field is empty
  const _numRaw = document.getElementById('f-num')?.value || '';
  const _status = document.querySelector('input[name="inv-status"]:checked')?.value || 'Draft';
  const _estPfx = STATE.settings.estPrefix || ('QT-' + new Date().getFullYear() + '-');
  const _invPfx = STATE.settings.prefix    || ('OT-' + new Date().getFullYear() + '-');
  let num = _numRaw;
  if (!num) {
    const _pfx = (_status === 'Estimate') ? _estPfx : _invPfx;
    let _seq = 1;
    STATE.invoices.forEach(inv => {
      const n = inv.num || inv.invoice_number || '';
      if (n.startsWith(_pfx)) { const s = parseInt(n.slice(_pfx.length), 10); if (!isNaN(s) && s >= _seq) _seq = s + 1; }
    });
    num = _pfx + String(_seq).padStart(3, '0');
    const _fnEl = document.getElementById('f-num'); if (_fnEl) _fnEl.value = num;
  }
 // const num     = document.getElementById('f-num')?.value||(STATE.settings.prefix||'INV-')+String(STATE.invoices.length+1).padStart(3,'0');
  const date    = document.getElementById('f-date')?.value||'';
  const due     = document.getElementById('f-due')?.value||'';
  // Service type: read from custom text input (select just triggers autofill)
  const svc = document.getElementById('f-service-custom')?.value || document.getElementById('f-service')?.value || '';
  const cname   = document.getElementById('f-cname')?.value||'Client Name';
  const cperson = document.getElementById('f-cperson')?.value||'';
  const cemail  = document.getElementById('f-cemail')?.value||'';
  const cwa     = document.getElementById('f-cwa')?.value||'';
  const cgst    = document.getElementById('f-cgst')?.value||'';
  const caddr   = document.getElementById('f-caddr')?.value||'';
  const disc    = parseFloat(document.getElementById('f-disc')?.value) || 0;
  const discType = document.getElementById('f-disc-type')?.value || 'pct';
  const notes   = document.getElementById('f-notes')?.value||'';
  const bank    = document.getElementById('f-bank')?.value||'';
  const tnc     = document.getElementById('f-tnc')?.value||'';
  const generatedBy = document.getElementById('f-generated-by')?.value || (STATE.settings.company ? STATE.settings.company + ' Invoice Manager' : 'Invoice Manager');
  const showGeneratedBy = document.getElementById('f-show-generated')?.checked !== false;
  const status  = document.querySelector('input[name="inv-status"]:checked')?.value||'Draft';
  const clientId = document.getElementById('f-client')?.value || '';
  const sym     = document.getElementById('f-currency')?.value||'₹';
  // Logos
  const companyLogo = document.getElementById('f-company-logo')?.value || STATE.settings.logo || '';
  // Ensure STATE.settings.logo is always up to date
  if (companyLogo && !STATE.settings.logo) STATE.settings.logo = companyLogo;
  const clientLogo  = document.getElementById('f-client-logo')?.value||'';
  const signature   = document.getElementById('f-signature')?.value || STATE.settings.signature || '';
  const qrUpload  = document.getElementById('f-qr')?.value || '';
  const sc = STATE.settings;
  // PDF options
  const popt = {
    bank:       document.getElementById('popt-bank')?.checked !== false,
    qr:         document.getElementById('popt-qr')?.checked || false,
    sign:       document.getElementById('popt-sign')?.checked || false,
    logo:       document.getElementById('popt-logo')?.checked !== false,
    clientLogo: document.getElementById('popt-client-logo')?.checked || false,
    notes:      document.getElementById('popt-notes')?.checked !== false,
    tnc:        document.getElementById('popt-tnc')?.checked !== false,
    gstCol:     document.getElementById('popt-gst-col')?.checked !== false,
    footer:     document.getElementById('popt-footer')?.checked !== false,
    watermark:    document.getElementById('popt-watermark')?.checked || false,
    paymentBlock:  document.getElementById('popt-payment-block')?.checked !== false,
    previousDue:   document.getElementById('popt-previous-due')?.checked !== false,
  };

  // Per-item GST totals
  let sub = 0, gstAmt = 0;
  formItems.forEach(item => {
    const line = (item.qty||1)*(item.rate||0);
    sub += line;
    gstAmt += line * (parseFloat(item.gst)||0) / 100;
  });
  const discAmt      = discType === 'fixed' ? Math.min(disc, sub) : sub * disc / 100;
  const discPct      = sub > 0 ? (discAmt / sub * 100) : 0;
  const discFactor   = sub > 0 ? (1 - discAmt/sub) : 1;
  const gstAfterDisc = gstAmt * discFactor;
  const grand        = sub - discAmt + gstAfterDisc;

  // Build a dynamic UPI QR that always reflects the current invoice amount.
  // Falls back to the uploaded static QR if no UPI ID is configured.
  const _dynUpi   = sc.upi || '';
  const _dynAmt   = grand.toFixed(2);
  const _dynName  = encodeURIComponent(sc.company || 'Merchant');
  const _dynNum   = num || 'Invoice';
  let qrUrl = qrUpload; // default: uploaded image
  if (_dynUpi && _dynAmt > 0) {
    const _upiString = `upi://pay?pa=${encodeURIComponent(_dynUpi)}&pn=${_dynName}&am=${_dynAmt}&cu=INR&tn=${encodeURIComponent(_dynNum)}`;
    qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=M&data=${encodeURIComponent(_upiString)}`;
  }

  const invId = STATE.editingInvoiceId ? String(STATE.editingInvoiceId) : '';
  return { tpl, num, date, due, svc, cname, cperson, cemail, cwa, cgst, caddr, disc: discPct, discRaw: disc, discType, notes, bank, tnc, status, sym, sub, discAmt, gstAmt: gstAfterDisc, grand, companyLogo, clientLogo, signature, qrUrl, popt, generatedBy, showGeneratedBy, invId, clientId };
}

function renderFormItems() {
  const el = document.getElementById('itemsList');
  if (!el) return;
  el.innerHTML = formItems.map(item => {
    const base     = (item.qty||1)*(item.rate||0);
    const gstRate  = parseFloat(item.gst ?? 0);
    const gstAmt   = base * gstRate / 100;
    const lineTotal = base + gstAmt;   // GST-inclusive total
    const itemType = item.itemType || 'Service';
    return `
    <div class="item-row" id="item-${item.id}">
      <div class="item-desc"><input value="${item.desc}" placeholder="Service / item description" oninput="updateItem(${item.id},'desc',this.value)"></div>
      <div class="item-type"><select onchange="updateItem(${item.id},'itemType',this.value)">
        ${(STATE.itemTypes||[{name:'Service'},{name:'Product'},{name:'Labour'},{name:'Other'}]).map(t=>`<option value="${t.name}" ${itemType===t.name?'selected':''}>${t.name}</option>`).join('')}
      </select></div>
      <div class="item-qty"><input type="number" value="${item.qty}" min="1" oninput="updateItem(${item.id},'qty',this.value)"></div>
      <div class="item-rate"><input type="number" value="${item.rate}" min="0" placeholder="0" oninput="updateItem(${item.id},'rate',this.value)"></div>
      <div class="item-amount" id="iamt-${item.id}" title="Amount (excl. GST)">${fmt_money(base)}</div>
      <div class="item-gst"><select onchange="updateItem(${item.id},'gst',this.value)">
        <option value="0" ${item.gst==0?'selected':''}>0%</option>
        <option value="5" ${item.gst==5?'selected':''}>5%</option>
        <option value="12" ${item.gst==12?'selected':''}>12%</option>
        <option value="18" ${item.gst==18?'selected':''}>18%</option>
        <option value="28" ${item.gst==28?'selected':''}>28%</option>
      </select></div>
      <div class="item-total" id="itot-${item.id}" title="Total (incl. GST)">${fmt_money(lineTotal)}</div>
      <button class="item-del" onclick="removeItem(${item.id})" title="Remove"><i class="fas fa-times"></i></button>
    </div>`;
  }).join('');
  calcTotals();
}

function removeItem(id) {
  formItems = formItems.filter(i=>i.id!==id);
  renderFormItems();
}

function updateItem(id, field, val) {
  const item = formItems.find(i=>i.id===id);
  if (!item) return;
  if (field === 'gst') {
    item.gst = (val !== '' && val !== null && val !== undefined) ? parseFloat(val) : 0;
  } else if (field === 'itemType') {
    item.itemType = val;
  } else {
    item[field] = field==='desc' ? val : (parseFloat(val)||0);
  }
  const base    = (item.qty||1)*(item.rate||0);
  const gstAmt  = base * (parseFloat(item.gst ?? 0)/100);
  const amt = document.getElementById('iamt-'+id);
  if (amt) amt.textContent = fmt_money(base);
  const tot = document.getElementById('itot-'+id);
  if (tot) tot.textContent = fmt_money(base + gstAmt);  // GST-inclusive
  calcTotals();
}

function clearClientLogo() {
  _ncLogoBase64 = '';
  _applyClientLogoPreview('');
  const fi = document.getElementById('nc-logo-file'); if (fi) fi.value = '';
  const ui = document.getElementById('nc-logo-url');  if (ui) ui.value = '';
}

function clearLogoField(targetId, previewId) {
  const el = document.getElementById(targetId); if (el) { el.value = ''; el.dispatchEvent(new Event('input')); }
  const prev = document.getElementById(previewId); if (prev) prev.innerHTML = '';
}

function updateClientLogoInitials() {
  const name  = document.getElementById('nc-name')?.value || '';
  const color = document.getElementById('nc-color')?.value || '#00897B';
  const preview = document.getElementById('nc-logo-preview');
  const initEl  = document.getElementById('nc-logo-initials');
  if (preview) preview.style.background = color;
  if (initEl)  initEl.textContent = getInitials(name) || '?';
}

function applyLogoBorderColor(img) {
  try {
    const canvas = document.createElement('canvas');
    const size = 24;
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, size, size);
    const data = ctx.getImageData(0, 0, size, size).data;
    const freq = {};
    let best = null, bestCount = 0;
    for (let i = 0; i < data.length; i += 4) {
      const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
      if (a < 80) continue; // skip transparent
      // skip near-white and near-black
      if (r > 230 && g > 230 && b > 230) continue;
      if (r < 25  && g < 25  && b < 25)  continue;
      // quantise to reduce noise
      const key = `${Math.round(r/16)*16},${Math.round(g/16)*16},${Math.round(b/16)*16}`;
      freq[key] = (freq[key] || 0) + 1;
      if (freq[key] > bestCount) { bestCount = freq[key]; best = key; }
    }
    if (best) {
      const [r,g,b] = best.split(',').map(Number);
      const wrap = img.closest('.cc-avatar');
      if (wrap) wrap.style.borderColor = `rgba(${r},${g},${b},0.7)`;
    }
  } catch(e) { /* cross-origin canvas taint — silently ignore */ }
}

function _applyClientLogoPreview(src) {
  const img      = document.getElementById('nc-logo-img');
  const initials = document.getElementById('nc-logo-initials');
  const preview  = document.getElementById('nc-logo-preview');
  if (src) {
    img.src = src; img.style.display = 'block';
    if (initials) initials.style.display = 'none';
    if (preview) {
      preview.style.border      = '3px solid #00897B';
      preview.style.boxShadow   = '0 0 0 3px rgba(0,137,123,.25), 0 2px 8px rgba(0,137,123,.35)';
    }
  } else {
    img.src = ''; img.style.display = 'none';
    if (initials) initials.style.display = '';
    if (preview) {
      preview.style.border    = '3px solid var(--border)';
      preview.style.boxShadow = 'none';
    }
  }
}

function _renderTagPills() {
  const wrap = document.getElementById('nc-tags-pills');
  if (!wrap) return;
  wrap.innerHTML = (_ncCurrentTags||[]).map(t => {
    const col = _tagColor(t);
    return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;padding:3px 9px;border-radius:10px;background:${col.bg};color:${col.text};border:1px solid ${col.border}">${t}<span onclick="removeTag('${t}')" style="cursor:pointer;opacity:.6;font-size:14px;line-height:1;margin-left:2px">&times;</span></span>`;
  }).join('');
}

function removeTag(tag) {
  _ncCurrentTags = (_ncCurrentTags||[]).filter(t => t !== tag);
  _renderTagPills();
}

async function sendEmailForClient(email, name, num, amount, due, service, d) {
  if (!email) { toast('⚠️ No email address for this client', 'warning'); return; }
  const sc    = STATE.settings;
  const ec    = sc.email_cfg || {};
  const invId = d?.invId || d?.id || d?.invoice_id || '';

  // Derive type from status if available, default to invoice
  const status   = d?.status || '';
  let emailType  = 'invoice';
  if      (status === 'Paid')     emailType = 'receipt';
  else if (status === 'Partial')  emailType = 'receipt';
  else if (status === 'Overdue')  emailType = 'overdue';
  else if (status === 'Estimate') emailType = 'estimate';

  // If SMTP configured — let the server resolve template + portal link
  if (ec.smtp_host && ec.smtp_user) {
    toast('📧 Sending email to ' + name + '…', 'info');
    try {
      const r = await api('/api/email.php', 'POST', {
        action:     'send',
        type:       emailType,
        invoice_id: invId,
        to:         email,
        to_name:    name,
      });
      if (r.success) {
        toast('✅ Email sent to ' + name + '!', 'success');
      } else {
        toast('⚠️ SMTP failed — opening email client instead', 'warning');
        const subj = encodeURIComponent(`Invoice #${num} from ${sc.company || ''}`);
        const body = encodeURIComponent(`Dear ${name},\n\nInvoice #${num} — ${amount}\nDue: ${due}\n\nThank you,\n${sc.company || ''}`);
        window.open(`mailto:${email}?subject=${subj}&body=${body}`, '_blank');
      }
    } catch(e) { toast('❌ Email error: ' + e.message, 'error'); }
  } else {
    // No SMTP — mailto fallback
    const subj = encodeURIComponent(`Invoice #${num} from ${sc.company || ''} – ${amount}`);
    const body = encodeURIComponent(`Dear ${name},\n\nInvoice #${num} — ${amount}\nDue: ${due}\nService: ${service}\n\nPay via UPI: ${sc.upi||''}\n\nThank you,\n${sc.company||''}\n${sc.phone||''}`);
    window.open(`mailto:${email}?subject=${subj}&body=${body}`, '_blank');
    toast('📧 Email client opened. Configure SMTP in Email Setup for direct sending.', 'info');
  }
}

function updateClientDropdown() {
  const s=document.getElementById('f-client-select'); if(!s) return;
  const active  = STATE.clients.filter(c => parseInt(c.active) !== 0 && c.status !== 'inactive');
  const inactive = STATE.clients.filter(c => parseInt(c.active) === 0 || c.status === 'inactive');
  let html = '<option value="">-- Quick Select Client --</option>'
    + '<option value="__onetime__" style="color:#E65100;font-weight:600">👤 One-Time / Walk-in Client (not saved)</option>'
    + active.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
  if (inactive.length) {
    html += `<optgroup label="─── Inactive Clients ───" style="color:#999">`;
    html += inactive.map(c=>`<option value="${c.id}" style="color:#aaa">${c.name} (inactive)</option>`).join('');
    html += '</optgroup>';
  }
  s.innerHTML = html;
}

function loadInvoiceIntoForm(inv) {
  const c = STATE.clients.find(x=>x.id===inv.client);
  document.getElementById('f-num').value      = inv.num || inv.invoice_number || '';
  document.getElementById('f-service-custom').value = inv.service || '';
  // Try to match the select option too
  const _fsSel = document.getElementById('f-service');
  if (_fsSel) {
    const _match = Array.from(_fsSel.options).find(o => o.value === (inv.service||''));
    _fsSel.value = _match ? (inv.service||'') : '';
  }
  document.getElementById('f-date').value     = inv.issued;
  document.getElementById('f-due').value      = inv.due;
  // Restore discount type + raw value.
  // discount_type comes from DB as 'percent' or 'flat'; HTML select uses 'pct' or 'fixed'.
  const _discAmt   = parseFloat(inv.discount_amt) || 0;
  const _discPct   = parseFloat(inv.disc || inv.discount_pct) || 0;
  // Translate DB enum value → HTML select value
  const _dbDiscType = inv.discount_type || '';
  let _discType;
  if (_dbDiscType === 'flat')    { _discType = 'fixed'; }
  else if (_dbDiscType === 'percent') { _discType = 'pct'; }
  else {
    // Legacy fallback: discount_amt is a whole integer → was fixed ₹
    _discType = (_discAmt > 0 && Number.isInteger(_discAmt)) ? 'fixed' : 'pct';
  }
  const _discRaw = _discType === 'fixed' ? _discAmt : _discPct;
  document.getElementById('f-disc').value = _discRaw;
  const _discTypeEl = document.getElementById('f-disc-type');
  if (_discTypeEl) _discTypeEl.value = _discType;
  document.getElementById('f-notes').value    = (inv.notes||'').replace(/\s*\|?\s*Partial payment received\..*$/i,'').trim();
  const _bankEl = document.getElementById('f-bank'); if(_bankEl) _bankEl.value = inv.bank||inv.bank_details||STATE.settings.defaultBank||'';
  const _tncEl  = document.getElementById('f-tnc');  if(_tncEl)  _tncEl.value  = inv.tnc||inv.terms||STATE.settings.defaultTnC||'';
  // f-bank and f-tnc set above
  document.getElementById('f-template').value = String(inv.template || inv.template_id || STATE.settings.activeTemplate || '2');
  document.getElementById('f-currency').value = inv.currency||'₹';
  document.getElementById('f-cname').value    = c ? c.name   : (inv.clientName || inv.client_name || '');
  document.getElementById('f-cperson').value  = c ? c.person : (inv.client_person || '');
  document.getElementById('f-cwa').value      = c ? c.wa     : (inv.client_wa    || inv.client_phone || '');
  document.getElementById('f-cemail').value   = c ? c.email  : (inv.client_email || '');
  document.getElementById('f-cgst').value     = c ? c.gst    : (inv.client_gst   || '');
  document.getElementById('f-caddr').value    = c ? c.addr   : (inv.client_addr  || inv.client_address || '');
  const sr = document.querySelectorAll('input[name="inv-status"]');
  sr.forEach(r => r.checked = r.value === inv.status);
  // Track original status so Draft→Estimate promotion is detectable on save
  STATE._editOrigStatus = inv.status || '';
  // ── Restore PDF options checkboxes from saved pdf_options ──
  let _savedPopt = inv.pdf_options || inv.popt || null;
  if (_savedPopt && typeof _savedPopt === 'string') { try { _savedPopt = JSON.parse(_savedPopt); } catch(e) { _savedPopt = null; } }
  if (_savedPopt && typeof _savedPopt === 'object') {
    const _sc = (id, val) => { const el = document.getElementById(id); if (el) el.checked = !!val; };
    _sc('popt-bank',       _savedPopt.bank       !== false);
    _sc('popt-qr',         !!_savedPopt.qr);
    _sc('popt-sign',       _savedPopt.sign        !== false);
    _sc('popt-logo',       _savedPopt.logo        !== false);
    _sc('popt-client-logo',!!_savedPopt.clientLogo);
    _sc('popt-notes',      _savedPopt.notes       !== false);
    _sc('popt-tnc',        _savedPopt.tnc         !== false);
    _sc('popt-gst-col',    _savedPopt.gstCol      !== false);
    _sc('popt-footer',     _savedPopt.footer      !== false);
    _sc('popt-watermark',    !!_savedPopt.watermark);
    _sc('popt-payment-block',_savedPopt.paymentBlock !== false);
    _sc('popt-previous-due',  !!_savedPopt.previousDue);
  }
  formItems = inv.items.map(i => ({ id: Date.now() + Math.random(), desc: i.desc||i.description||'', itemType: i.itemType||i.item_type||'Service', qty: parseFloat(i.qty||i.quantity)||1, gst: (i.gst!==undefined&&i.gst!==null&&i.gst!==''?parseFloat(i.gst):i.gstRate!==undefined&&i.gstRate!==null&&i.gstRate!==''?parseFloat(i.gstRate):i.gst_rate!==undefined&&i.gst_rate!==''?parseFloat(i.gst_rate):18), rate: parseFloat(i.rate)||0 }));
  renderFormItems();
  livePreview();
}
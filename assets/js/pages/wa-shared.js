// ================================================================
//  assets/js/wa-shared.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  Loaded by: dashboard.php (WA card), invoices.php (Send WA
//  buttons), clients.js (Msg/Statement buttons), reminders.php,
//  whatsapp.php, msglog.php.
//
//  This unblocks the WA-dependent pieces that were deferred in
//  earlier pages:
//    - dashboard.js's renderDashWAActivity()/WA card links
//    - invoices.js's sendWAForInvoice()/bulkSendWA() (was a no-op)
//    - clients.js's sendWAMessage()/sendAccountStatement() (was a
//      no-op)
//
//  NOT included here — these are whatsapp.php-page-specific UI
//  glue (manual send box, festival bulk sender, character counters)
//  and will come with whatsapp.js when that page is built:
//    sendManualWA, fillWaManualPhone, sendFestivalBulk,
//    previewFestivalMsg, waUpdateCounter, testWA
// ================================================================

// ── Message templates (defaults, overridable per-tenant in Settings → WhatsApp) ──
function getDefaultWATpl(type) {
  const d = {
    estimate: `Hi {client_name}! 👋

📋 *Estimation / Quotation*
From: *{company_name}*

We have prepared a cost estimate for your requirements:

🔢 Quote No: *#{invoice_no}*
📅 Date: *{issue_date}*
💰 Estimated Amount: *{currency}{amount}*
⏳ Valid Until: *{due_date}*
📋 Service: {service}

{item_list}

⚠️ *Please note: This is an ESTIMATE only, not a final invoice. Actual charges may vary based on the final scope of work.*

👁️ View & Review your estimate online:
{invoice_link}

To *accept* this estimate, reply *APPROVED*.
To request changes, reply with your feedback.

Thank you for considering {company_name}! 🙏
📞 {company_phone} | ✉ {company_email}`,

    inv: `Hi {client_name}! 👋

*Invoice #{invoice_no}* from *{company_name}* is ready.

📋 Service: {service}
📅 Issue Date: {issue_date}
⏳ Due Date: *{due_date}*
💰 Amount: *{currency}{amount}*

{item_list}

💳 *Pay via UPI:* {upi}
🏦 {bank_details}

🔗 *View & Download Invoice:*
{invoice_link}

Thank you for choosing {company_name}!
📞 {company_phone} | ✉ {company_email}`,

    paid: `Hi {client_name}! ✅

Payment received for *Invoice #{invoice_no}*{settlement_discount_line}

💰 Amount Received: *{currency}{amount}*
📅 Date: {issue_date}
📋 Service: {service}

🔗 *View Receipt:*
{invoice_link}

Your account is now clear. Thank you! 🙏
We look forward to serving you again.

— *{company_name}*
📞 {company_phone}`,

    remind: `Hi {client_name}! 🔔 *Payment Reminder*

*Invoice #{invoice_no}* for *{currency}{amount}* is due on *{due_date}*

📋 Service: {service}

Please arrange payment at your earliest convenience.

💳 *UPI:* {upi}
🏦 {bank_details}

🔗 *View Invoice:*
{invoice_link}

— {company_name} | 📞 {company_phone}`,

    overdue: `Hi {client_name}! ⚠️ *Overdue Notice*

*Invoice #{invoice_no}* for *{currency}{amount}* was due on *{due_date}*
Overdue by: *{days_overdue} days*

📋 Service: {service}

Please clear this immediately to avoid any inconvenience.

💳 *UPI:* {upi}
🏦 {bank_details}

🔗 *View Invoice:*
{invoice_link}

— {company_name} | 📞 {company_phone}`,

    recurring: `Hi {client_name}! 🔁

*Recurring Invoice #{invoice_no}* from *{company_name}* is ready.

📋 Service: {service}
📅 Issue Date: {issue_date}
⏳ Due Date: *{due_date}*
💰 Amount: *{currency}{amount}*

{item_list}

💳 *Pay via UPI:* {upi}
🏦 {bank_details}

{outstanding_dues}

🔗 *View & Download Invoice:*
{invoice_link}

Thank you for choosing {company_name}!
📞 {company_phone} | ✉ {company_email}`,

    followup: `Hi {client_name},

This is a follow-up for *Invoice #{invoice_no}* (*{currency}{amount}*).
⚠️ Still overdue by *{days_overdue} days*

📋 Service: {service}

Kindly process payment immediately or contact us to discuss.

💳 *UPI:* {upi}

🔗 *View Invoice:*
{invoice_link}

— {company_name} | 📞 {company_phone} | ✉ {company_email}`,

    partial_receipt: `Hi {client_name}! 💚

*Partial Payment Received* for Invoice #{invoice_no}

✅ Paid: *{paid_amount}*
⏳ Remaining: *{remaining_amount}*
📋 Invoice Total: {currency}{amount}
📅 Date: {issue_date}
📋 Service: {service}

Please clear the remaining balance by *{due_date}*.
💳 UPI: {upi}
🏦 {bank_details}

🔗 *View Invoice:*
{invoice_link}

Thank you! — *{company_name}*
📞 {company_phone}`,

    split_receipt: `Hi {client_name}! ⚡

*Split Payment Recorded* for Invoice #{invoice_no}

💰 Amount: *{currency}{amount}*
📋 Payment split across multiple methods
📅 Date: {issue_date}
📋 Service: {service}

🔗 *View Receipt:*
{invoice_link}

Your account is now clear. Thank you! 🙏
— *{company_name}* | 📞 {company_phone}`,

    festival: `Hi {client_name}! 🎉

Warm *festival greetings* from the entire team at *{company_name}*!

May this occasion bring you joy, prosperity, and success. 🌟

Thank you for your continued trust and support. We are grateful for your partnership. 🙏

*{company_name}*
📞 {company_phone} | ✉ {company_email}`,
  };
  return d[type] || '';
}

// ── Fill a template string with invoice/client/settings data ──────
function formatWAMsg(tpl, inv, client, settings) {
  const sc = settings || STATE.settings;
  const c = client || STATE.clients.find(x => String(x.id) === String(inv?.client)) || {};
  const today = new Date().toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short', year: 'numeric' });
  const dueDate = inv.due || inv.due_date || '';
  const dueFmt = dueDate ? new Date(dueDate).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short', year: 'numeric' }) : '';
  const issuedFmt = (inv.issued || inv.issued_date) ? new Date(inv.issued || inv.issued_date).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short', year: 'numeric' }) : today;
  const sym = inv.currency || '₹';
  const grandTotal = parseFloat(inv.amount || inv.grand_total) || 0;
  const amount = fmt_money(grandTotal, sym);
  const daysOverdue = dueDate ? Math.max(0, Math.floor((new Date() - new Date(dueDate)) / 86400000)) : 0;
  const items = (inv.items || []).map(i => {
    const qty = parseFloat(i.qty || i.quantity || 1);
    const rate = parseFloat(i.rate || 0);
    const gst = parseFloat(i.gst || i.gst_rate || i.gstRate || 0);
    const line = qty * rate;
    const lineInclGst = line + (line * gst / 100);
    return `  • ${i.desc || i.description || ''}: ${fmt_money(lineInclGst, sym)}`;
  }).join('\n');

  let paidAmt = inv._paidAmt;
  let remainingAmt = inv._remainingAmt;
  if (paidAmt === undefined || remainingAmt === undefined) {
    const invId = String(inv.id || inv._dbId || inv.invId || '');
    const invNum = String(inv.num || inv.invoice_number || '');
    if (STATE.payments && (invId || invNum)) {
      let pmts = invId ? STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === invId) : [];
      if (pmts.length === 0 && invNum) pmts = STATE.payments.filter(p => p.invoice_number && String(p.invoice_number) === invNum);
      const totalPaidFromDB = pmts.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
      paidAmt = paidAmt !== undefined ? paidAmt : totalPaidFromDB;
      remainingAmt = remainingAmt !== undefined ? remainingAmt : Math.max(0, grandTotal - totalPaidFromDB);
    } else {
      paidAmt = paidAmt !== undefined ? paidAmt : 0;
      remainingAmt = remainingAmt !== undefined ? remainingAmt : grandTotal;
    }
  }

  const invId = String(inv.id || inv._dbId || '');
  let portalLink = '';
  if (invId && _portalTokenCache && _portalTokenCache[invId]) portalLink = _portalBaseURL() + '?t=' + _portalTokenCache[invId];
  else if (invId && typeof _portalTokenMap !== 'undefined' && _portalTokenMap[invId]) portalLink = _portalBaseURL() + '?t=' + _portalTokenMap[invId].token;

  return (tpl || '')
    .replace(/{client_name}/g, c.name || inv.clientName || inv.client_name || 'Valued Client')
    .replace(/{invoice_no}/g, inv.num || inv.invoice_number || '')
    .replace(/{amount}/g, amount)
    .replace(/{currency}/g, sym)
    .replace(/{due_date}/g, dueFmt)
    .replace(/{issue_date}/g, issuedFmt)
    .replace(/{service}/g, inv.service || inv.service_type || '')
    .replace(/{company_name}/g, sc.company || '')
    .replace(/{company_phone}/g, sc.phone || '')
    .replace(/{company_email}/g, sc.email || '')
    .replace(/{upi}/g, sc.upi || '')
    .replace(/{bank_details}/g, sc.defaultBank || '')
    .replace(/{days_overdue}/g, String(daysOverdue))
    .replace(/{item_list}/g, items || '')
    .replace(/{status}/g, inv.status || '')
    .replace(/{outstanding_dues}/g, inv._outstandingDues || '')
    .replace(/{total_payable}/g, inv._totalPayable ? fmt_money(parseFloat(inv._totalPayable)) : '')
    .replace(/{invoice_link}/g, portalLink)
    .replace(/{settlement_discount}/g, (() => {
      const iid = String(inv.id || inv._dbId || '');
      if (!iid || !STATE.payments) return '';
      const pmts = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === iid);
      const total = pmts.reduce((s, p) => s + parseFloat(p.settlement_discount || 0), 0);
      return total > 0.001 ? fmt_money(total, sym) : '';
    })())
    .replace(/{settlement_discount_line}/g, (() => {
      const iid = String(inv.id || inv._dbId || '');
      const fromInv = parseFloat(inv._settleDisc || 0);
      if (fromInv > 0.001) return `\n✂ Settlement Discount: -${fmt_money(fromInv, sym)}`;
      if (!iid || !STATE.payments) return '';
      const pmts = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === iid);
      const total = pmts.reduce((s, p) => s + parseFloat(p.settlement_discount || 0), 0);
      return total > 0.001 ? `\n✂ Settlement Discount: -${fmt_money(total, sym)}` : '';
    })())
    .replace(/{paid_amount}/g, fmt_money(paidAmt, sym))
    .replace(/{remaining_amount}/g, fmt_money(remainingAmt, sym))
    .replace(/{payment_method}/g, inv._payMethod || '')
    .replace(/{instalment_no}/g, String(inv._instalmentNo || ''));
}

// ══════════════════════════════════════════
// MESSAGE LOG — dual-write: fast localStorage cache (used to detect
// an in-flight "sending" entry) + the real DB write via api/wa_log.php.
// msglog.js's actual table render reads from the DB via WA_LOG.fetchLog()
// (shared-data.js), not from this localStorage cache.
// ══════════════════════════════════════════
const MSG_LOG_KEY = 'optms_msg_log';
const MSG_LOG_MAX = 500;

function getMsgLog() {
  try {
    const log = JSON.parse(localStorage.getItem(MSG_LOG_KEY) || '[]');
    const fiveMinsAgo = Date.now() - 5 * 60 * 1000;
    let cleaned = false;
    log.forEach(e => {
      if (e.status === 'sending' && e.ts && new Date(e.ts).getTime() < fiveMinsAgo) {
        e.status = 'failed'; e.error = e.error || 'Send interrupted (page closed)'; cleaned = true;
      }
    });
    if (cleaned) saveMsgLog(log);
    return log;
  } catch (e) { return []; }
}
function saveMsgLog(log) {
  try { localStorage.setItem(MSG_LOG_KEY, JSON.stringify(log.slice(-MSG_LOG_MAX))); } catch (e) { }
}

function logWAMessage({ inv, client, type, msg, status, error, wamid }) {
  const log = getMsgLog();
  const resolvedPhone = (client && (client.wa || client.whatsapp || client.phone)) || (inv && (inv.client_wa || inv.client_phone)) || '';
  const resolvedInvId = inv ? String(inv.id || inv._dbId || '') : '';

  if (status !== 'sending' && resolvedInvId) {
    const existing = log.findIndex(e => e.status === 'sending' && e.inv_id === resolvedInvId && e.type === (type || 'unknown'));
    if (existing !== -1) {
      log[existing].status = status || 'sent_web';
      log[existing].error = error || '';
      if (wamid) log[existing].wamid = wamid;
      saveMsgLog(log);
      api('/api/wa_log.php', 'POST', { id: log[existing].id, wamid: wamid || '', type: log[existing].type, status: status || 'sent_web', error: error || '' })
        .catch(e => console.warn('[wa_log] DB update failed:', e.message));
      _updateMsglogBadge(log);
      return;
    }
  }

  const entry = {
    id: Date.now() + '_' + Math.random().toString(36).slice(2, 6),
    wamid: wamid || '', ts: new Date().toISOString(), type: type || 'unknown', status: status || 'sent_web',
    client: (client && client.name) || (inv && (inv.clientName || inv.client_name)) || '—',
    phone: resolvedPhone || '—', inv_id: resolvedInvId,
    inv_num: inv ? (inv.num || inv.invoice_number || '') : '',
    inv_amt: inv ? fmt_money(parseFloat(inv.amount || inv.grand_total || 0), inv.currency || '₹') : '',
    inv_status: inv ? (inv.status || '') : '', msg: msg || '', error: error || '',
  };
  log.unshift(entry);
  saveMsgLog(log);
  api('/api/wa_log.php', 'POST', {
    id: entry.id, wamid: entry.wamid || '', ts: entry.ts, type: entry.type, status: entry.status,
    client: entry.client, phone: entry.phone !== '—' ? entry.phone : '',
    inv_id: entry.inv_id || '', inv_num: entry.inv_num || '', inv_amt: entry.inv_amt || '',
    inv_status: entry.inv_status || '', msg: entry.msg || '', error: entry.error || '',
  }).catch(e => console.warn('[wa_log] DB write failed:', e.message));
  _updateMsglogBadge(log);
}

function _updateMsglogBadge(log) {
  const failed = log.filter(e => e.status === 'failed').length;
  const badge = document.getElementById('badge-msglog');
  if (badge) {
    if (failed > 0) { badge.style.display = ''; badge.textContent = failed; badge.style.background = 'var(--red)'; }
    else badge.style.display = 'none';
  }
}

// ── Send via Meta WhatsApp Business API ───────────────────────
async function sendWABusinessMsg(toPhone, message, token, pid, tplOpts) {
  const body = tplOpts
    ? { token, pid, to: toPhone, type: 'template', template_name: tplOpts.name, template_lang: tplOpts.lang || 'en', template_params: tplOpts.params || [], message }
    : { token, pid, to: toPhone, type: 'text', message };
  const res = await fetch('api/wa_send.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch (e) { throw new Error('Server error: ' + text.substring(0, 200)); }
  if (!res.ok || data.error) throw new Error(data.error || 'API error ' + res.status);
  return data;
}

// Build ordered template params for approved WhatsApp Business templates
function buildWATplParams(tplName, inv, client, settings) {
  const sc = settings || STATE.settings;
  const c = client || {};
  const dueDate = inv.due || inv.due_date || '';
  const dueFmt = dueDate ? new Date(dueDate).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short', year: 'numeric' }) : '';
  const issueFmt = (inv.issued || inv.issued_date) ? new Date(inv.issued || inv.issued_date).toLocaleDateString(_moneyLocale(), { day: '2-digit', month: 'short', year: 'numeric' }) : '';
  const amount = String(parseFloat(inv.amount || inv.grand_total) || 0);
  const daysOver = dueDate ? String(Math.max(0, Math.floor((new Date() - new Date(dueDate)) / 86400000))) : '0';

  const tplInvId = String(inv.id || inv._dbId || '');
  let tplPortalLink = '';
  if (tplInvId && _portalTokenCache && _portalTokenCache[tplInvId]) tplPortalLink = _portalBaseURL() + '?t=' + _portalTokenCache[tplInvId];
  else if (tplInvId && typeof _portalTokenMap !== 'undefined' && _portalTokenMap[tplInvId]) tplPortalLink = _portalBaseURL() + '?t=' + _portalTokenMap[tplInvId].token;

  const settleDiscStr = (() => {
    const sd = parseFloat(inv._settleDisc || 0);
    if (sd > 0.001) return fmt_money(sd, inv.currency || '₹');
    const invId = String(inv.id || inv._dbId || '');
    if (!invId || !STATE.payments) return '0';
    const pmts = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === invId);
    const total = pmts.reduce((s, p) => s + parseFloat(p.settlement_discount || 0), 0);
    return total > 0.001 ? fmt_money(total, inv.currency || '₹') : '0';
  })();
  const paidAmtStr = (() => {
    const invId = String(inv.id || inv._dbId || '');
    const fromInv = parseFloat(inv._paidAmt || 0);
    if (fromInv > 0) return fmt_money(fromInv, inv.currency || '₹');
    if (!invId || !STATE.payments) return '0';
    return fmt_money(STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === invId).reduce((s, p) => s + parseFloat(p.amount || 0), 0), inv.currency || '₹');
  })();
  const remAmtStr = (() => {
    const fromInv = parseFloat(inv._remainingAmt !== undefined ? inv._remainingAmt : -1);
    if (fromInv >= 0) return fmt_money(fromInv, inv.currency || '₹');
    const grand = parseFloat(inv.amount || inv.grand_total || 0);
    const invId = String(inv.id || inv._dbId || '');
    if (!invId || !STATE.payments) return fmt_money(grand, inv.currency || '₹');
    const paid = STATE.payments.filter(p => p.invoice_id && String(p.invoice_id) === invId).reduce((s, p) => s + parseFloat(p.amount || 0), 0);
    return fmt_money(Math.max(0, grand - paid), inv.currency || '₹');
  })();
  const common = {
    client_name: c.name || inv.client_name || 'Valued Client', invoice_no: inv.num || inv.invoice_number || '',
    amount, currency: inv.currency || '₹', due_date: dueFmt, issue_date: issueFmt,
    service: inv.service || inv.service_type || '', company_name: sc.company || '', upi: sc.upi || '',
    company_phone: sc.phone || '', company_email: sc.email || '', bank_details: sc.defaultBank || sc.bank || '',
    item_list: (inv.items || []).map(i => `• ${i.desc || ''}: ${(inv.currency || '₹')}${((i.qty || 1) * (i.rate || 0)).toLocaleString(_moneyLocale())}`).join('') || '',
    days_overdue: daysOver, portal_link: tplPortalLink, settlement_discount: settleDiscStr,
    paid_amount: paidAmtStr, remaining_amount: remAmtStr,
  };
  const maps = {
    invoice: ['invoice_no', 'company_name', 'client_name', 'service', 'issue_date', 'due_date', 'amount', 'upi', 'portal_link'],
    paid: ['client_name', 'invoice_no', 'amount', 'settlement_discount', 'issue_date', 'company_name', 'portal_link'],
    partial: ['client_name', 'invoice_no', 'paid_amount', 'remaining_amount', 'due_date', 'portal_link'],
    reminder: ['client_name', 'invoice_no', 'amount', 'due_date', 'upi', 'company_name', 'portal_link'],
    balance_reminder: ['client_name', 'invoice_no', 'paid_amount', 'remaining_amount', 'due_date', 'portal_link'],
    overdue: ['client_name', 'invoice_no', 'amount', 'days_overdue', 'upi', 'portal_link', 'company_phone', 'company_name'],
    followup: ['client_name', 'invoice_no', 'amount', 'days_overdue', 'upi', 'company_phone', 'portal_link'],
    festival: ['client_name', 'company_name', 'company_phone'],
    estimate: ['company_name', 'client_name', 'invoice_no', 'issue_date', 'amount', 'due_date', 'service', 'portal_link'],
    invoice_created: ['client_name', 'invoice_no', 'amount', 'due_date', 'upi', 'company_name', 'portal_link'],
    payment_reminder: ['client_name', 'invoice_no', 'amount', 'due_date', 'upi', 'company_name', 'portal_link'],
    payment_overdue: ['client_name', 'invoice_no', 'amount', 'days_overdue', 'upi', 'portal_link', 'company_phone', 'company_name'],
    payment_received: ['client_name', 'invoice_no', 'amount', 'settlement_discount', 'issue_date', 'company_name', 'portal_link'],
    invoice_followup: ['client_name', 'invoice_no', 'amount', 'days_overdue', 'upi', 'company_phone', 'portal_link'],
    partial_payment: ['client_name', 'invoice_no', 'paid_amount', 'remaining_amount', 'due_date', 'portal_link'],
    festival_greeting: ['client_name', 'company_name', 'company_phone'],
    estimate_created: ['client_name', 'company_name', 'invoice_no', 'issue_date', 'amount', 'due_date', 'service', 'portal_link'],
  };
  const paramKeys = maps[tplName] || Object.keys(common);
  return paramKeys.map(k => common[k] || '');
}

// ── Send WA (API first, wa.me web fallback) ───────────────────
async function sendWA(phone, message, tplName, inv, client) {
  const wa = STATE.settings.wa || {};
  const token = wa.token || '';
  const pid = wa.pid || '';
  const clean = String(phone).replace(/\D/g, '');
  if (!clean) throw new Error('No phone number');
  if (token && pid) {
    const TPL_KEY_MAP = {
      estimate_created: 'estimate', invoice_created: 'invoice', payment_received: 'paid',
      partial_payment: 'partial', split_payment: 'paid', payment_overdue: 'overdue',
      payment_reminder: 'reminder', balance_reminder: 'balance_reminder', invoice_followup: 'followup',
      recurring_invoice: 'recurring', festival: 'festival',
    };
    const tplKey = TPL_KEY_MAP[tplName] || tplName;
    const useTemplate = wa.msg_mode === 'template' && tplKey && wa['tpl_name_' + tplKey];
    if (inv && inv.id) {
      const _pid = String(inv.id || inv._dbId || '');
      if (_pid && !_portalTokenCache[_pid]) {
        try { const _pr = await api('/api/portal.php', 'POST', { invoice_id: parseInt(_pid) }); if (_pr && _pr.token) _portalTokenCache[_pid] = _pr.token; }
        catch (e) { /* continue without portal link */ }
      }
    }
    const tplOpts = useTemplate ? { name: wa['tpl_name_' + tplKey], lang: wa['tpl_lang_' + tplKey] || 'en_US', params: inv ? buildWATplParams(tplKey, inv, client, STATE.settings) : [] } : null;
    return await sendWABusinessMsg(clean, message, token, pid, tplOpts);
  }
  if (wa.allow_web_fallback) {
    window.open('https://wa.me/' + (clean.length === 10 ? '91' + clean : clean) + '?text=' + encodeURIComponent(message), '_blank');
    return null;
  }
  throw new Error('WhatsApp Business API not configured (no auto-send, web fallback disabled)');
}

// ── Send WA for a specific invoice (picks template by status) ──
async function sendWAForInvoice(inv) {
  if ((inv.status || '') === 'Draft') { toast('⚠️ Draft invoices cannot be sent via WhatsApp. Change status to Pending or Estimate first.', 'warning'); return; }
  const clientId = inv.client || inv.client_id;
  const c = STATE.clients.find(x => String(x.id) === String(clientId)) || {};
  const cByName = !c.id ? (STATE.clients.find(x => x.name === (inv.clientName || inv.client_name)) || {}) : c;
  const client = c.id ? c : cByName;
  const phone = (client.wa || client.whatsapp || client.phone || inv.client_wa || inv.client_phone || '').replace(/\D/g, '');
  const clientName = client.name || inv.clientName || inv.client_name || 'Client';
  if (!phone) { toast('⚠️ No WhatsApp number for client "' + clientName + '"', 'warning'); return; }
  const wa = STATE.settings.wa || {};
  let tplKey, tplDefault, tplName, statusLabel;
  const status = inv.status || '';
  if (status === 'Estimate') { tplKey = wa.tpl_estimate; tplDefault = getDefaultWATpl('estimate'); tplName = 'estimate_created'; statusLabel = 'Estimate'; }
  else if (status === 'Paid') { tplKey = wa.tpl_paid; tplDefault = getDefaultWATpl('paid'); tplName = 'payment_received'; statusLabel = 'Payment Receipt'; }
  else if (status === 'Partial') { tplKey = wa.tpl_partial; tplDefault = getDefaultWATpl('partial_receipt'); tplName = 'partial_payment'; statusLabel = 'Partial Receipt'; }
  else if (status === 'Overdue') { tplKey = wa.tpl_overdue; tplDefault = getDefaultWATpl('overdue'); tplName = 'payment_overdue'; statusLabel = 'Overdue Alert'; }
  else { tplKey = wa.tpl_inv; tplDefault = getDefaultWATpl('inv'); tplName = 'invoice_created'; statusLabel = 'Invoice'; }
  const tpl = tplKey || tplDefault;
  const invIdForPortal = String(inv.id || inv._dbId || '');
  if (invIdForPortal && !_portalTokenCache[invIdForPortal]) {
    try { const pr = await api('/api/portal.php', 'POST', { invoice_id: parseInt(invIdForPortal) }); if (pr && pr.token) _portalTokenCache[invIdForPortal] = pr.token; }
    catch (e) { /* portal link unavailable, continue without it */ }
  }
  const msg = formatWAMsg(tpl, inv, client, STATE.settings);
  logWAMessage({ inv, client, type: tplName, msg, status: 'sending' });
  try {
    const result = await sendWA(phone, msg, tplName, inv, client);
    const wamid = result?.wamid || result?.messages?.[0]?.id || '';
    logWAMessage({ inv, client, type: tplName, msg, status: result ? 'sent_api' : 'sent_web', wamid });
    const _toastName = client.name || inv.clientName || inv.client_name || 'client';
    toast(result ? `✅ ${statusLabel} sent to ${_toastName}!` : `📱 WhatsApp opened for ${_toastName}`, 'success');
  } catch (e) {
    logWAMessage({ inv, client, type: tplName, msg, status: 'failed', error: e.message });
    toast('❌ ' + e.message, 'error');
  }
}

// ── Generic "Msg" button (clients.js) ──────────────────────────
function sendWAMessage(wa, name, num, amount, due) {
  if (!wa) { toast('⚠️ No WhatsApp number for this client', 'warning'); return; }
  const token = STATE.settings.wa?.token || '';
  if (token) {
    toast('📱 Sending via WhatsApp Business API…', 'info');
    setTimeout(() => toast(`✅ WhatsApp sent to ${name}!`, 'success'), 1500);
  } else {
    const tpl = `Hi {client_name}! Invoice #{invoice_no} for {amount} from ${STATE.settings.company || '{company_name}'}. Due: {due_date}.`;
    const msg = tpl.replace('{client_name}', name).replace('{invoice_no}', num).replace('{amount}', amount).replace('{due_date}', due).replace('{upi}', STATE.settings.upi || '');
    const num2 = wa.replace(/\D/g, '');
    window.open(`https://wa.me/${num2}?text=${encodeURIComponent(msg)}`, '_blank');
    toast(`📱 Opening WhatsApp for ${name}`, 'success');
  }
}

// ── Account statement (clients.js "Statement" button) ─────────
function sendAccountStatement(clientId) {
  const c = STATE.clients.find(x => String(x.id) === String(clientId));
  if (!c) return;
  const unpaid = STATE.invoices
    .filter(i => String(i.client || i.client_id || i.clientId) === String(clientId) && ['Pending', 'Overdue', 'Partial'].includes(i.status))
    .sort((a, b) => new Date(a.issued || a.created_at || 0) - new Date(b.issued || b.created_at || 0));
  if (!unpaid.length) { toast(`✅ ${c.name} has no outstanding dues`, 'success'); return; }

  const sc = STATE.settings || {};
  const today = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
  const totalAmt = unpaid.reduce((s, i) => s + parseFloat(i.amount || i.grand_total || 0), 0);
  const overdueInvs = unpaid.filter(i => i.status === 'Overdue');
  const lines = unpaid.map(i => {
    const num = i.num || i.invoice_number || 'Invoice';
    const amt = fmt_money(parseFloat(i.amount || i.grand_total || 0));
    const due = i.due || i.due_date || '—';
    const status = i.status === 'Overdue' ? '🔴 OVERDUE' : i.status === 'Partial' ? '💛 PARTIAL' : '⏳ PENDING';
    return `  • *${num}* — ${amt} | Due: ${due} | ${status}`;
  }).join('\n');
  const msg = `━━━━━━━━━━━━━━━━━━━━━━
📋 *ACCOUNT STATEMENT*
━━━━━━━━━━━━━━━━━━━━━━
From: *${sc.company || 'Our Company'}*
To: *${c.name}*
Date: ${today}

*Outstanding Invoices:*
${lines}
──────────────────────
💰 *Total Outstanding: ${fmt_money(totalAmt)}*
${overdueInvs.length > 0 ? `⚠️ ${overdueInvs.length} invoice${overdueInvs.length > 1 ? 's are' : ' is'} overdue — please clear immediately.\n` : ''}
💳 *Pay via UPI:* ${sc.upi || '—'}
🏦 ${sc.defaultBank || ''}

Please arrange payment at the earliest.
Thank you for your continued business. 🙏

— *${sc.company || ''}*
📞 ${sc.phone || ''} | ✉ ${sc.email || ''}`;

  const waToken = STATE.settings.wa?.token || '';
  const waPid = STATE.settings.wa?.pid || '';
  const waPhone = (c.wa || c.whatsapp || c.phone || '').replace(/\D/g, '');

  Swal.fire({
    title: `Statement — ${c.name}`,
    html: `
      <div style="text-align:left;margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
          <span style="font-size:13px;color:#666">${unpaid.length} unpaid invoice${unpaid.length > 1 ? 's' : ''}</span>
          <span style="font-size:14px;font-weight:800;color:#C62828">${fmt_money(totalAmt)}</span>
        </div>
        <div style="border:1px solid #eee;border-radius:8px;overflow:hidden;margin-bottom:12px">
          ${unpaid.map(i => `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #f5f5f5">
              <div>
                <div style="font-size:12px;font-weight:700">${i.num || i.invoice_number || 'Invoice'}</div>
                <div style="font-size:11px;color:#999">Due: ${i.due || i.due_date || '—'}</div>
              </div>
              <div style="text-align:right">
                <div style="font-size:13px;font-weight:700">${fmt_money(parseFloat(i.amount || i.grand_total || 0))}</div>
                <div style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;display:inline-block;
                  background:${i.status === 'Overdue' ? '#FFEBEE' : i.status === 'Partial' ? '#FFF8E1' : '#E3F2FD'};
                  color:${i.status === 'Overdue' ? '#C62828' : i.status === 'Partial' ? '#E65100' : '#1565C0'}">${i.status}</div>
              </div>
            </div>`).join('')}
        </div>
        <textarea style="width:100%;height:120px;font-size:11px;font-family:monospace;border:1px solid #ddd;border-radius:6px;padding:8px;resize:none;box-sizing:border-box" id="swal-stmt-msg">${msg}</textarea>
        ${waToken && waPhone ? `<div style="margin-top:6px;font-size:11px;color:#1a7a3c"><i class="fab fa-whatsapp"></i> WA → <strong>${waPhone}</strong> <span style="color:#888">(free-form, 24h session)</span></div>` : `<div style="margin-top:6px;font-size:11px;color:#E65100"><i class="fas fa-exclamation-triangle"></i> WA API not configured — use Email or Copy</div>`}
        ${c.email ? `<div style="margin-top:3px;font-size:11px;color:#1565C0"><i class="fas fa-envelope"></i> Email → <strong>${c.email}</strong></div>` : `<div style="margin-top:3px;font-size:11px;color:#999"><i class="fas fa-envelope"></i> No email on file</div>`}
      </div>`,
    showCancelButton: true, showDenyButton: true,
    confirmButtonText: waToken && waPhone ? `<i class="fab fa-whatsapp"></i> Send via WA` : `📋 Copy Text`,
    denyButtonText: c.email ? `<i class="fas fa-envelope"></i> Send Email` : `📋 Copy Text`,
    cancelButtonText: 'Cancel',
    confirmButtonColor: waToken && waPhone ? '#25D366' : '#1976D2', denyButtonColor: '#1976D2',
    customClass: { popup: 'swal-compact' },
    footer: `<button onclick="navigator.clipboard?.writeText(document.getElementById('swal-stmt-msg')?.value||'').then(()=>Swal.showValidationMessage('📋 Copied!')).catch(()=>{})" style="background:none;border:none;color:#1976D2;cursor:pointer;font-size:12px"><i class="fas fa-copy"></i> Copy Text</button>`,
  }).then(async result => {
    const finalMsg = document.getElementById('swal-stmt-msg')?.value || msg;
    if (result.isConfirmed) {
      if (waToken && waPhone) {
        const stmtInv = { id: null, num: 'STMT', invoice_number: 'STMT', client: clientId, clientName: c.name, amount: totalAmt, grand_total: totalAmt, status: 'Statement' };
        logWAMessage({ inv: stmtInv, client: c, type: 'statement', msg: finalMsg, status: 'sending' });
        try {
          const res = await sendWABusinessMsg(waPhone, finalMsg, waToken, waPid, null);
          logWAMessage({ inv: stmtInv, client: c, type: 'statement', msg: finalMsg, status: res?.success ? 'sent_api' : 'sent_web' });
          toast(res?.success ? `✅ Statement sent to ${c.name} via WA` : `📱 WhatsApp opened for ${c.name}`, 'success');
        } catch (e) {
          const clean = (c.wa || '').replace(/\D/g, '');
          const link = 'https://wa.me/' + (clean.length === 10 ? '91' + clean : clean) + '?text=' + encodeURIComponent(finalMsg);
          window.open(link, '_blank');
          toast('📱 WhatsApp opened (API unavailable)', 'info');
        }
      } else {
        navigator.clipboard?.writeText(finalMsg).then(() => toast('📋 Statement copied to clipboard', 'success')).catch(() => toast('📋 Select and copy from the text area', 'info'));
      }
    } else if (result.isDenied) {
      if (c.email) _sendStatementEmail(c, unpaid, totalAmt, sc);
      else navigator.clipboard?.writeText(finalMsg).then(() => toast('📋 Statement copied to clipboard', 'success')).catch(() => toast('📋 Select and copy from the text area', 'info'));
    }
  });
}

async function _sendStatementEmail(c, unpaid, totalAmt, sc) {
  if (!c.email) { toast('⚠️ No email address for ' + c.name, 'warning'); return; }
  const today = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
  const overdueCount = unpaid.filter(i => i.status === 'Overdue').length;
  const rowsHtml = unpaid.map(i => {
    const num = i.num || i.invoice_number || '—';
    const amt = fmt_money(parseFloat(i.amount || i.grand_total || 0));
    const due = i.due || i.due_date || '—';
    const bgCol = i.status === 'Overdue' ? '#FFEBEE' : i.status === 'Partial' ? '#FFF8E1' : '#E3F2FD';
    const txCol = i.status === 'Overdue' ? '#C62828' : i.status === 'Partial' ? '#E65100' : '#1565C0';
    return `<tr style="border-bottom:1px solid #f0f0f0">
      <td style="padding:8px 12px;font-size:13px;font-weight:700;font-family:monospace">${num}</td>
      <td style="padding:8px 12px;font-size:13px;color:#555">${due}</td>
      <td style="padding:8px 12px;font-size:13px;font-weight:700;text-align:right">${amt}</td>
      <td style="padding:8px 12px;text-align:center"><span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:${bgCol};color:${txCol}">${i.status}</span></td>
    </tr>`;
  }).join('');
  const htmlBody = `<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333">
  <div style="background:#1565C0;padding:20px 24px;border-radius:8px 8px 0 0">
    <h2 style="color:#fff;margin:0;font-size:18px">📋 Account Statement</h2>
    <p style="color:#BBDEFB;margin:4px 0 0;font-size:13px">${sc.company || ''}</p>
  </div>
  <div style="background:#fff;padding:20px 24px;border:1px solid #e0e0e0;border-top:none">
    <table style="width:100%;margin-bottom:16px;font-size:13px">
      <tr><td style="color:#777;padding:3px 0">To:</td><td style="font-weight:700">${c.name}</td></tr>
      <tr><td style="color:#777;padding:3px 0">Date:</td><td>${today}</td></tr>
      <tr><td style="color:#777;padding:3px 0">Invoices:</td><td>${unpaid.length} outstanding</td></tr>
    </table>
    <table style="width:100%;border-collapse:collapse;border:1px solid #e0e0e0;border-radius:6px;overflow:hidden;margin-bottom:16px">
      <thead><tr style="background:#F5F5F5">
        <th style="padding:8px 12px;text-align:left;font-size:12px;color:#555">Invoice</th>
        <th style="padding:8px 12px;text-align:left;font-size:12px;color:#555">Due Date</th>
        <th style="padding:8px 12px;text-align:right;font-size:12px;color:#555">Amount</th>
        <th style="padding:8px 12px;text-align:center;font-size:12px;color:#555">Status</th>
      </tr></thead>
      <tbody>${rowsHtml}</tbody>
    </table>
    <div style="background:#FFF3E0;border-left:4px solid #F57C00;padding:12px 16px;border-radius:4px;margin-bottom:16px">
      <div style="font-size:13px;color:#777">Total Outstanding</div>
      <div style="font-size:22px;font-weight:800;color:#C62828">${fmt_money(totalAmt)}</div>
      ${overdueCount > 0 ? `<div style="font-size:12px;color:#E65100;margin-top:4px">⚠️ ${overdueCount} invoice${overdueCount > 1 ? 's are' : ' is'} overdue — please clear immediately.</div>` : ''}
    </div>
    ${sc.upi ? `<div style="background:#E8F5E9;padding:10px 14px;border-radius:6px;font-size:13px;color:#2E7D32;margin-bottom:16px">💳 Pay via UPI: <strong>${sc.upi}</strong>${sc.defaultBank ? '<br>🏦 ' + sc.defaultBank : ''}</div>` : ''}
    <p style="font-size:13px;color:#555;margin:0">Please arrange payment at the earliest. Thank you for your continued business.</p>
  </div>
  <div style="background:#F5F5F5;padding:12px 24px;border-radius:0 0 8px 8px;font-size:12px;color:#888;text-align:center">
    ${sc.company || ''} ${sc.phone ? '| 📞 ' + sc.phone : ''} ${sc.email ? '| ✉ ' + sc.email : ''}
  </div>
</div>`;
  const subject = `Account Statement — ${unpaid.length} Outstanding Invoice${unpaid.length > 1 ? 's' : ''} | ${fmt_money(totalAmt)}`;
  try {
    toast('📧 Sending statement...', 'info');
    const r = await api('/api/email.php', 'POST', { action: 'send', type: 'statement', to: c.email, to_name: c.name, subject, body: htmlBody, invoice_id: null });
    if (r?.success) toast(`✅ Statement emailed to ${c.email}`, 'success');
    else toast('❌ Email failed: ' + (r?.error || 'Unknown error'), 'error');
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

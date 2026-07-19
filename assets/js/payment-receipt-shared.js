// ================================================================
//  assets/js/payment-receipt-shared.js
//  Requires: common.js, shared-data.js (loaded before this file).
//
//  Shared between pages/payments/payments-product.php and
//  payments-service.php: the receipt viewer/printer and the
//  "revert deleted flag" action. Neither depends on which page's
//  PMT/PMTS list state it was called from — viewReceipt() takes an
//  explicit list argument, and printReceiptModal() reads the
//  resolved record it stashed rather than re-deriving it from a
//  page-specific list (see MPA FIX comment below — the original
//  hardcoded PMT.list, which would throw on the Service page where
//  only PMTS exists).
// ================================================================

function viewReceiptSvc(i){ viewReceipt(i, PMTS.list); }

async function revertPaymentDelete(idx, list) {
  const p = (list||PMT.list)[idx];
  if (!p || !p.id) return;
  const _revertResult = await Swal.fire({ title: 'Revert Payment Flag?', html: 'This will mark the payment as <b>active</b> again.', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Revert', cancelButtonText: 'Cancel', confirmButtonColor: '#00897B', customClass: { popup: 'swal-compact' } });
  if (!_revertResult.isConfirmed) return;
  try {
    await api('api/payments.php?id=' + parseInt(p.id), 'PATCH', { invoice_deleted: false });
    // Update in STATE
    const sp = STATE.payments.find(x => String(x.id) === String(p.id));
    if (sp) { sp._invoiceDeleted = false; sp.invoice_deleted = false; }
    toast('↩ Payment flag reverted — now showing as active', 'success');
    // MPA FIX: old SPA branched on business type since both renderPayments()
    // and renderPaymentsService() always existed (single page, everything
    // loaded). Only one of them is ever defined here — check which.
    if (typeof renderPayments === 'function') renderPayments();
    else if (typeof renderPaymentsService === 'function') renderPaymentsService();
  } catch(e) {
    toast('❌ Revert failed: ' + e.message, 'error');
  }
}
function viewReceipt(i, list){
  const p=(list||PMT.list)[i]; if(!p) return;
  const sc=STATE.settings;
  const df=p.date?new Date(p.date).toLocaleDateString(_moneyLocale(),{day:'2-digit',month:'long',year:'numeric'}):p.date;
  document.getElementById('receiptBody').innerHTML=`
    <div style="text-align:center;margin-bottom:18px">
      <div style="font-size:20px;font-weight:800;color:var(--teal)">${sc.company}</div>
      <div style="font-size:11px;color:var(--muted)">${sc.address} · ${sc.phone}</div>
    </div>
    <div style="border:2px dashed var(--teal);border-radius:10px;padding:18px;margin-bottom:16px;text-align:center">
      <div style="font-size:36px;color:#388E3C">✓</div>
      <div style="font-weight:700;margin-bottom:4px">Payment Received</div>
      <div style="font-size:28px;font-weight:800;color:var(--teal);font-family:var(--mono)">${fmt_money(p.amount)}</div>
    </div>
    <table style="width:100%;border-collapse:collapse">
      ${[['Date',df],['Invoice #',p.inv],['Client',p.client],['Method',p.method],['Txn ID',p.txn||'—'],['Status',p.status]].map(([k,v])=>`<tr><td style="padding:8px 12px;border-bottom:1px solid var(--border);color:var(--muted);font-size:13px;width:40%">${k}</td><td style="padding:8px 12px;border-bottom:1px solid var(--border);font-weight:600;font-size:13px">${v}</td></tr>`).join('')}
    </table>
    <div style="margin-top:14px;text-align:center;font-size:10px;color:var(--muted)">Computer-generated receipt · ${STATE.settings.company || 'Invoice Manager'}</div>`;
  // MPA FIX: old SPA stashed just the index (STATE._rcptIdx) and let
  // printReceiptModal() re-look-it-up via the hardcoded PMT.list —
  // fine when PMT always existed (single page). On the Service
  // payments page only PMTS exists, so that lookup would throw
  // "PMT is not defined". Storing the resolved record directly here
  // instead, so printReceiptModal() doesn't need to know which list
  // (or which page) it came from.
  STATE._rcptRecord = p;
  openModal('modal-receipt');
}
function printReceiptModal(){
  const p=STATE._rcptRecord; if(!p) return;
  const sc=STATE.settings;
  const w=window.open('','_blank','width=600,height=700');
  const df=p.date?new Date(p.date).toLocaleDateString(_moneyLocale(),{day:'2-digit',month:'long',year:'numeric'}):p.date;
  w.document.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:sans-serif;padding:40px}.no-print{display:flex;gap:10px;margin-bottom:20px;padding:10px;background:#f5f5f5;border-radius:8px}@media print{.no-print{display:none!important}}</style></head><body>
  <div class="no-print"><button onclick="window.print()" style="padding:8px 20px;background:#00897B;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:bold">Print</button><button onclick="window.close()" style="padding:8px 16px;border:1px solid #ddd;border-radius:7px;cursor:pointer">Close</button></div>
  <div style="max-width:480px;margin:0 auto;border:1px solid #eee;border-radius:12px;overflow:hidden">
    <div style="background:#00897B;color:#fff;padding:20px;text-align:center"><h2>${sc.company}</h2><p style="font-size:12px;opacity:.8">${sc.address}</p></div>
    <div style="padding:24px;text-align:center"><div style="font-size:40px;color:#388E3C">✓</div><div style="font-weight:700">Payment Received</div><div style="font-size:28px;font-weight:800;color:#00897B">${fmt_money(p.amount)}</div></div>
    <table style="width:100%;border-collapse:collapse;padding:0 24px 24px">
      ${[['Date',df],['Invoice',p.inv],['Client',p.client],['Method',p.method],['Txn ID',p.txn||'—']].map(([k,v])=>`<tr><td style="padding:8px 24px;border-bottom:1px solid #eee;color:#666">${k}</td><td style="padding:8px 24px;border-bottom:1px solid #eee;font-weight:600">${v}</td></tr>`).join('')}
    </table>
  </div></body></html>`);
  w.document.close();
}

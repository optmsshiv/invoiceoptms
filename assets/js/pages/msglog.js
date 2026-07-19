// ============================================================
// msglog.js — page-specific JS for pages/msglog.php
// Depends on: common.js, shared-data.js, wa-shared.js
// renderWALog() and the WA_LOG object already live in wa-shared.js;
// this file only adds the CSV export, which is specific to this page.
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['settings']);
  renderWALog();
});

function exportMsgLog() {
  const log = getMsgLog();
  if (!log.length) { toast('⚠️ No messages to export', 'warning'); return; }
  const header = ['Time','Type','Client','Phone','Invoice','Amount','Inv Status','Msg Status','Message','Error'];
  const rows   = log.map(e => [
    e.ts ? new Date(e.ts).toLocaleString(_moneyLocale()) : '',
    e.type, e.client, e.phone, e.inv_num, e.inv_amt, e.inv_status, e.status,
    '"'+(e.msg||'').replace(/"/g,'""')+'"',
    '"'+(e.error||'').replace(/"/g,'""')+'"',
  ].join(','));
  const csv  = [header.join(','), ...rows].join('\n');
  const blob = new Blob([csv], { type:'text/csv' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = 'message_log_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
  toast('📥 Message log exported', 'success');
}
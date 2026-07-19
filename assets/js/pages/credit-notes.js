// ================================================================
//  assets/js/credit_notes.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — fully self-contained in the SPA too
//  (its own print-preview builder, no shared PDF template engine).
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['creditNotes', 'clients', 'settings']);
  renderCreditNotes();
});

function renderCreditNotes() {
  const search = (document.getElementById('cn-search')?.value || '').toLowerCase();
  const statusF = document.getElementById('cn-status-filter')?.value || '';

  let cns = STATE.creditNotes.filter(cn => {
    if (statusF && cn.status !== statusF) return false;
    if (!search) return true;
    return (cn.cn_number || '').toLowerCase().includes(search)
        || (cn.client_name || '').toLowerCase().includes(search)
        || (cn.invoice_number || '').toLowerCase().includes(search)
        || (cn.reason || '').toLowerCase().includes(search);
  });

  const total   = STATE.creditNotes.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
  const issued  = STATE.creditNotes.filter(c => c.status === 'Issued').reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
  const applied = STATE.creditNotes.filter(c => c.status === 'Applied').reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
  document.getElementById('cn-summary').innerHTML = `
    <div class="kpi-card"><div class="kpi-label">Total Credit Notes</div><div class="kpi-value">${STATE.creditNotes.length}</div></div>
    <div class="kpi-card"><div class="kpi-label">Total Value</div><div class="kpi-value" style="color:var(--purple)">${fmt_money(total)}</div></div>
    <div class="kpi-card"><div class="kpi-label">Issued (pending apply)</div><div class="kpi-value" style="color:#E65100">${fmt_money(issued)}</div></div>
    <div class="kpi-card"><div class="kpi-label">Applied</div><div class="kpi-value" style="color:var(--green)">${fmt_money(applied)}</div></div>`;

  const statusColor = { Draft: '#9E9E9E', Issued: '#E65100', Applied: '#388E3C', Void: '#B71C1C' };
  const tbody = document.getElementById('cn-tbody');
  if (!cns.length) {
    tbody.innerHTML = `<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--muted)"><i class="fas fa-file-circle-minus" style="font-size:24px;display:block;margin-bottom:8px"></i>No credit notes yet</td></tr>`;
    document.getElementById('cn-info').textContent = '';
    return;
  }
  tbody.innerHTML = cns.map(cn => {
    const sc = statusColor[cn.status] || '#888';
    return `<tr>
      <td><strong style="font-family:var(--mono);font-size:12px;color:var(--purple)">${cn.cn_number || '—'}</strong></td>
      <td style="font-size:12px;color:var(--muted)">${cn.invoice_number || '—'}</td>
      <td style="font-size:13px">${cn.client_name || '—'}</td>
      <td style="font-size:12px">${cn.issued_date ? fmt_date(new Date(cn.issued_date)) : '—'}</td>
      <td style="font-family:var(--mono);font-size:13px;font-weight:700;color:var(--purple)">${fmt_money(cn.amount || 0)}</td>
      <td style="font-size:12px;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${cn.reason || ''}">${cn.reason || '—'}</td>
      <td><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;background:${sc}18;color:${sc}">${cn.status}</span></td>
      <td style="white-space:nowrap">
        <button onclick="previewCreditNote('${cn.id}')" title="Preview" style="padding:4px 8px;background:var(--teal-bg);color:var(--teal);border:1px solid var(--teal);border-radius:6px;cursor:pointer;font-size:11px;margin-right:3px"><i class="fas fa-eye"></i></button>
        <button onclick="openCreditNoteModal(null,'${cn.id}')" title="Edit" style="padding:4px 8px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:11px;margin-right:3px"><i class="fas fa-edit"></i></button>
        <button onclick="changeCNStatus('${cn.id}','${cn.status}')" title="Change status" style="padding:4px 8px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:11px;margin-right:3px"><i class="fas fa-exchange-alt"></i></button>
        <button onclick="deleteCreditNote('${cn.id}')" title="Delete" style="padding:4px 8px;background:var(--red-bg);color:var(--red);border:1px solid #FFCDD2;border-radius:6px;cursor:pointer;font-size:11px"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
  document.getElementById('cn-info').textContent = `${cns.length} credit note${cns.length !== 1 ? 's' : ''}`;
}

function filterCreditNotes(v) { renderCreditNotes(); }

// NOTE: openCreditNoteModal(inv, editId) — the `inv` param supported
// pre-filling from an invoice's row menu (e.g. "Issue credit note for
// this invoice") in the SPA. The row menu isn't built yet on
// invoices.js, so for now this is only reachable via the "New Credit
// Note" button here (inv will be null/undefined, which is handled).
async function openCreditNoteModal(inv, editId) {
  const editCN = editId ? STATE.creditNotes.find(c => String(c.id) === String(editId)) : null;
  const defaultClient = inv ? (inv.clientName || inv.client_name || '') : (editCN?.client_name || '');
  const defaultInvNum = inv ? (inv.num || inv.invoice_number || '') : (editCN?.invoice_number || '');
  const defaultAmt    = inv ? (parseFloat(inv.amount) || 0) : (parseFloat(editCN?.amount) || 0);
  const defaultReason = editCN?.reason || (inv?.cancel_reason ? `Invoice cancelled: ${inv.cancel_reason}` : '');

  const clientOptions = STATE.clients.map(c => `<option value="${c.name}"${c.name === defaultClient ? ' selected' : ''}>${c.name}</option>`).join('');

  const { value: formData, isConfirmed } = await Swal.fire({
    title: editCN ? `Edit Credit Note ${editCN.cn_number}` : '📄 New Credit Note',
    width: 520,
    html: `
      <div style="text-align:left">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
          <div>
            <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Client *</label>
            <input id="cn-client" list="cn-client-list" value="${defaultClient}" placeholder="Client name"
              style="width:100%;padding:8px;border:1.5px solid var(--border2);border-radius:7px;font-size:13px;box-sizing:border-box;margin-top:3px">
            <datalist id="cn-client-list">${clientOptions}</datalist>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Linked Invoice #</label>
            <input id="cn-inv-num" value="${defaultInvNum}" placeholder="e.g. INV-2026-014 (optional)"
              style="width:100%;padding:8px;border:1.5px solid var(--border2);border-radius:7px;font-size:13px;box-sizing:border-box;margin-top:3px">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
          <div>
            <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Credit Amount *</label>
            <input id="cn-amount" type="number" min="0" step="0.01" value="${defaultAmt || ''}" placeholder="0.00"
              style="width:100%;padding:8px;border:1.5px solid var(--border2);border-radius:7px;font-size:13px;box-sizing:border-box;margin-top:3px">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Issue Date *</label>
            <input id="cn-date" type="date" value="${editCN?.issued_date || new Date().toISOString().slice(0, 10)}"
              style="width:100%;padding:8px;border:1.5px solid var(--border2);border-radius:7px;font-size:13px;box-sizing:border-box;margin-top:3px">
          </div>
        </div>
        <div style="margin-bottom:10px">
          <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Reason for Credit Note *</label>
          <textarea id="cn-reason" placeholder="e.g. Service not delivered, overcharge, invoice cancelled…" rows="3"
            style="width:100%;padding:8px;border:1.5px solid var(--border2);border-radius:7px;font-size:13px;resize:vertical;box-sizing:border-box;margin-top:3px;font-family:var(--font)">${defaultReason}</textarea>
        </div>
        <div style="margin-bottom:4px">
          <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Internal Notes</label>
          <textarea id="cn-notes" placeholder="Optional internal notes…" rows="2"
            style="width:100%;padding:8px;border:1.5px solid var(--border2);border-radius:7px;font-size:13px;resize:vertical;box-sizing:border-box;margin-top:3px;font-family:var(--font)">${editCN?.notes || ''}</textarea>
        </div>
        ${editCN ? `<div style="margin-top:8px">
          <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Status</label>
          <select id="cn-status" style="width:100%;padding:8px;border:1.5px solid var(--border2);border-radius:7px;font-size:13px;margin-top:3px">
            ${['Draft', 'Issued', 'Applied', 'Void'].map(s => `<option${s === editCN.status ? ' selected' : ''}>${s}</option>`).join('')}
          </select>
        </div>` : ''}
      </div>`,
    showCancelButton: true,
    confirmButtonText: editCN ? 'Save Changes' : 'Create Credit Note',
    confirmButtonColor: '#6A1B9A',
    customClass: { popup: 'swal-compact' },
    preConfirm: () => {
      const client = document.getElementById('cn-client').value.trim();
      const amount = parseFloat(document.getElementById('cn-amount').value);
      const reason = document.getElementById('cn-reason').value.trim();
      const date   = document.getElementById('cn-date').value;
      if (!client) { Swal.showValidationMessage('Client name is required'); return false; }
      if (!amount || amount <= 0) { Swal.showValidationMessage('Amount must be greater than 0'); return false; }
      if (!reason) { Swal.showValidationMessage('Reason is required'); return false; }
      if (!date) { Swal.showValidationMessage('Issue date is required'); return false; }
      return {
        client_name: client,
        invoice_number: document.getElementById('cn-inv-num').value.trim(),
        invoice_id: inv?.id || editCN?.invoice_id || null,
        amount, issued_date: date, reason,
        notes: document.getElementById('cn-notes').value.trim(),
        status: document.getElementById('cn-status')?.value || 'Draft',
      };
    },
  });
  if (!isConfirmed) return;

  try {
    if (editCN) {
      await api('/api/credit_notes.php?id=' + editCN.id, 'PUT', formData);
      const idx = STATE.creditNotes.findIndex(c => String(c.id) === String(editCN.id));
      if (idx !== -1) STATE.creditNotes[idx] = { ...STATE.creditNotes[idx], ...formData };
      toast('✅ Credit note updated', 'success');
    } else {
      const res = await api('/api/credit_notes.php', 'POST', formData);
      if (res.id) {
        STATE.creditNotes.unshift({ id: res.id, cn_number: res.cn_number, ...formData });
        toast(`📄 Created ${res.cn_number}`, 'success');
      }
    }
    logActivity('credit_note', editCN ? `Updated ${editCN.cn_number}` : `Created credit note`, formData.client_name);
    renderCreditNotes();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function changeCNStatus(id, currentStatus) {
  const statuses = ['Draft', 'Issued', 'Applied', 'Void'].filter(s => s !== currentStatus);
  const { value: newStatus, isConfirmed } = await Swal.fire({
    title: 'Change CN Status', input: 'select',
    inputOptions: Object.fromEntries(statuses.map(s => [s, s])),
    showCancelButton: true, confirmButtonText: 'Update', confirmButtonColor: '#6A1B9A',
    customClass: { popup: 'swal-compact' },
  });
  if (!isConfirmed || !newStatus) return;
  try {
    await api('/api/credit_notes.php?id=' + id, 'PATCH', { status: newStatus });
    const cn = STATE.creditNotes.find(c => String(c.id) === String(id));
    if (cn) cn.status = newStatus;
    toast(`✅ Status → ${newStatus}`, 'success');
    renderCreditNotes();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

async function deleteCreditNote(id) {
  const cn = STATE.creditNotes.find(c => String(c.id) === String(id));
  const { isConfirmed } = await Swal.fire({
    title: `Delete ${cn?.cn_number || 'Credit Note'}?`, text: 'This cannot be undone.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#E53935',
    customClass: { popup: 'swal-compact' },
  });
  if (!isConfirmed) return;
  try {
    await api('/api/credit_notes.php?id=' + id, 'DELETE');
    STATE.creditNotes = STATE.creditNotes.filter(c => String(c.id) !== String(id));
    toast('🗑 Deleted', 'success');
    renderCreditNotes();
  } catch (e) { toast('❌ ' + e.message, 'error'); }
}

function previewCreditNote(id) {
  const cn = STATE.creditNotes.find(c => String(c.id) === String(id));
  if (!cn) return;
  const sc = STATE.settings;
  const html = buildCreditNoteHTML(cn, sc);
  const win = window.open('', '_blank', 'width=860,height=1000');
  win.document.write(`<!DOCTYPE html><html><head><title>${cn.cn_number}</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box}body{margin:0;font-family:'Public Sans',sans-serif;background:#f5f5f5}
    @media print{body{background:#fff}.no-print{display:none}}</style></head>
    <body><div class="no-print" style="padding:12px;background:#1a1a2e;display:flex;gap:10px;align-items:center">
      <button onclick="window.print()" style="padding:7px 18px;background:#00897B;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600">🖨 Print / Save PDF</button>
      <button onclick="window.close()" style="padding:7px 18px;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px;cursor:pointer">Close</button>
    </div>${html}</body></html>`);
  win.document.close();
}

function buildCreditNoteHTML(cn, sc) {
  const logo = sc.logo ? `<img src="${sc.logo}" style="height:52px;object-fit:contain">` : `<div style="font-size:22px;font-weight:800;color:#fff">${sc.company || 'Company'}</div>`;
  return `<div style="max-width:794px;margin:20px auto;background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.12);border-radius:10px;overflow:hidden;font-family:'Public Sans',sans-serif">
    <div style="background:linear-gradient(135deg,#4A148C,#6A1B9A);padding:28px 32px;display:flex;justify-content:space-between;align-items:center">
      <div>${logo}<div style="color:rgba(255,255,255,.7);font-size:11px;margin-top:6px">${sc.address || ''}</div></div>
      <div style="text-align:right">
        <div style="border:1.5px solid rgba(255,255,255,.4);border-radius:5px;color:rgba(255,255,255,.8);font-size:9px;font-weight:700;letter-spacing:1px;padding:3px 8px;display:inline-block;margin-bottom:6px">CREDIT NOTE</div>
        <div style="font-size:22px;font-weight:800;color:#fff;font-family:monospace">${cn.cn_number}</div>
        <div style="color:rgba(255,255,255,.65);font-size:11px;margin-top:4px">Date: ${cn.issued_date ? fmt_date(new Date(cn.issued_date)) : '—'}</div>
        ${cn.invoice_number ? `<div style="color:rgba(255,255,255,.55);font-size:10px;margin-top:2px">Against: ${cn.invoice_number}</div>` : ''}
      </div>
    </div>
    <div style="padding:24px 32px;display:grid;grid-template-columns:1fr auto;gap:20px;border-bottom:1px solid #eee">
      <div>
        <div style="font-size:9px;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px">Issued To</div>
        <div style="font-size:15px;font-weight:700;color:#212121">${cn.client_name || '—'}</div>
      </div>
      <div style="text-align:right">
        <div style="font-size:9px;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px">Credit Amount</div>
        <div style="font-size:28px;font-weight:800;color:#6A1B9A;font-family:monospace">${fmt_money(cn.amount || 0)}</div>
        <div style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;margin-top:6px;
          background:${{ Draft: '#F5F5F5', Issued: '#FFF3E0', Applied: '#E8F5E9', Void: '#FFEBEE' }[cn.status] || '#F5F5F5'};
          color:${{ Draft: '#757575', Issued: '#E65100', Applied: '#388E3C', Void: '#C62828' }[cn.status] || '#757575'}">${cn.status}</div>
      </div>
    </div>
    <div style="padding:20px 32px;border-bottom:1px solid #eee">
      <div style="font-size:9px;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">Reason for Credit Note</div>
      <div style="font-size:13px;color:#424242;line-height:1.6;background:#F8F4FF;border-left:3px solid #6A1B9A;padding:10px 14px;border-radius:0 6px 6px 0">${cn.reason || '—'}</div>
    </div>
    ${cn.notes ? `<div style="padding:16px 32px;border-bottom:1px solid #eee">
      <div style="font-size:9px;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px">Notes</div>
      <div style="font-size:12px;color:#757575">${cn.notes}</div>
    </div>` : ''}
    <div style="padding:16px 32px;background:#FAFAFA;display:flex;justify-content:space-between;align-items:center">
      <div style="font-size:11px;color:#9E9E9E">${sc.company || ''} • ${sc.email || ''} • ${sc.phone || ''}</div>
      <div style="font-size:10px;color:#BDBDBD">Generated by ${sc.company ? sc.company + ' Invoice Manager' : 'Invoice Manager'}</div>
    </div>
  </div>`;
}

function exportCreditNotesCSV() {
  const rows = [['CN #', 'Invoice #', 'Client', 'Date', 'Amount', 'Reason', 'Status']];
  STATE.creditNotes.forEach(cn => rows.push([cn.cn_number || '', cn.invoice_number || '', cn.client_name || '', cn.issued_date || '', cn.amount || 0, cn.reason || '', cn.status || '']));
  const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'credit_notes_' + new Date().toISOString().slice(0, 10) + '.csv';
  a.click();
}

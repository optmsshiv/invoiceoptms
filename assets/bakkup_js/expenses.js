// ================================================================
//  assets/js/expenses.js
//  Requires: common.js, shared-data.js (loaded before this file).
//  No MPA adjustments needed — self-contained in the SPA too.
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
  await loadCoreData(['expenses', 'invoices', 'settings']);
  renderExpenses();
});

const EXP = { page: 1, per: 10, list: [] };

function renderExpenses() {
  updateExpenseCatDropdowns();
  api('api/expenses.php').then(r => {
    if (r && r.data) STATE.expenses = r.data;
    _populateExpenseMonthFilter();
    EXP.list = [...STATE.expenses].sort((a, b) => new Date(b.date) - new Date(a.date));
    EXP.page = 1; _renderExpSummary(); _renderExpTable();
  }).catch(() => {
    _populateExpenseMonthFilter();
    EXP.list = [...STATE.expenses].sort((a, b) => new Date(b.date) - new Date(a.date));
    EXP.page = 1; _renderExpSummary(); _renderExpTable();
  });
}

function updateExpenseCatDropdowns() {
  const cats = STATE.expenseCategories || [];
  const opts = cats.map(c => `<option>${escHtml(c.name)}</option>`).join('');
  const sel = document.getElementById('exp-category');
  if (sel) { const cur = sel.value; sel.innerHTML = `<option value="">— Select —</option>${opts}`; sel.value = cur; }
  const filter = document.getElementById('exp-cat-filter');
  if (filter) { const cur = filter.value; filter.innerHTML = `<option value="">All Categories</option>${opts}`; filter.value = cur; }
}

function _populateExpenseMonthFilter() {
  const sel = document.getElementById('exp-month-filter');
  if (!sel) return;
  const months = [...new Set(STATE.expenses.map(e => e.date?.slice(0, 7)))].sort().reverse();
  sel.innerHTML = '<option value="">All Time</option>' + months.map(m => `<option value="${m}">${m}</option>`).join('');
}

function _renderExpSummary() {
  const el = document.getElementById('exp-summary-cards');
  if (!el) return;
  const total = STATE.expenses.reduce((s, e) => s + parseFloat(e.amount || 0), 0);
  const now = new Date();
  const thisMonth = STATE.expenses.filter(e => e.date?.slice(0, 7) === `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
  const monthTotal = thisMonth.reduce((s, e) => s + parseFloat(e.amount || 0), 0);
  const catTotals = {};
  STATE.expenses.forEach(e => { catTotals[e.category] = (catTotals[e.category] || 0) + parseFloat(e.amount || 0); });
  const topCat = Object.entries(catTotals).sort((a, b) => b[1] - a[1])[0];
  const revenue = STATE.invoices.filter(i => i.status === 'Paid').reduce((s, i) => s + parseFloat(i.amount || 0), 0);
  const expRatio = revenue > 0 ? Math.round(total / revenue * 100) : 0;
  const cards = [
    { l: 'Total Expenses', v: fmt_money(total), ic: 'fa-wallet', col: '#E65100', bg: '#fbe9e7' },
    { l: 'This Month', v: fmt_money(monthTotal), ic: 'fa-calendar-day', col: '#1976D2', bg: '#e3f2fd' },
    { l: 'Top Category', v: topCat ? topCat[0] : '—', ic: 'fa-tag', col: '#7B1FA2', bg: '#f3e5f5' },
    { l: 'Expense / Revenue', v: expRatio + '%', ic: 'fa-chart-pie', col: '#388E3C', bg: '#e8f5e9' },
  ];
  el.innerHTML = cards.map(c => `<div class="stat-card">
    <div class="stat-icon" style="background:${c.bg};color:${c.col}"><i class="fas ${c.ic}"></i></div>
    <div class="stat-body"><div class="stat-val" style="font-size:18px">${c.v}</div><div class="stat-lbl">${c.l}</div></div>
  </div>`).join('');
}

function _renderExpTable() {
  const tbody = document.getElementById('exp-tbody');
  const info = document.getElementById('exp-info');
  if (!tbody) return;
  const s = (EXP.page - 1) * EXP.per, e = s + EXP.per;
  const pg = EXP.list.slice(s, e);
  if (!EXP.list.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)"><i class="fas fa-wallet" style="font-size:28px;opacity:.2;display:block;margin-bottom:8px"></i>No expenses yet. <a onclick="openAddExpenseModal()" style="color:var(--teal);cursor:pointer">Add one →</a></td></tr>`;
    if (info) info.textContent = '0 expenses';
    return;
  }
  tbody.innerHTML = pg.map(exp => {
    const col = getExpCatColor(exp.category);
    return `<tr>
      <td>${exp.date || '—'}</td>
      <td><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:${pastelBg(col)};color:${col}">${exp.category || '—'}</span></td>
      <td style="font-weight:600">${exp.vendor || '—'}</td>
      <td style="color:var(--muted)">${exp.method || '—'}</td>
      <td style="font-family:var(--mono);font-weight:700;color:#C62828">${fmt_money(exp.amount || 0)}</td>
      <td style="color:var(--muted);font-size:12px">${exp.notes || '—'}</td>
      <td>
        <button onclick="editExpense('${exp.id}')" style="padding:4px 8px;background:var(--blue-bg);color:var(--blue);border:1px solid #90caf9;border-radius:6px;cursor:pointer;font-size:11px;margin-right:4px"><i class="fas fa-edit"></i></button>
        <button onclick="deleteExpense('${exp.id}')" style="padding:4px 8px;background:var(--red-bg);color:var(--red);border:1px solid #ffcdd2;border-radius:6px;cursor:pointer;font-size:11px"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
  if (info) info.textContent = `${EXP.list.length} expenses · Total: ${fmt_money(EXP.list.reduce((s, e) => s + parseFloat(e.amount || 0), 0))}`;
  _renderExpPagination();
}

function _renderExpPagination() {
  const el = document.getElementById('exp-pagination');
  if (!el) return;
  const total = Math.ceil(EXP.list.length / EXP.per);
  if (total <= 1) { el.innerHTML = ''; return; }
  el.innerHTML = Array.from({ length: total }, (_, i) =>
    `<button class="page-btn${EXP.page === i + 1 ? ' active' : ''}" onclick="expPage(${i + 1})">${i + 1}</button>`
  ).join('');
}
function expPage(p) { EXP.page = p; _renderExpTable(); }

function filterExpenses(val) {
  const s = val.toLowerCase();
  EXP.list = STATE.expenses.filter(e =>
    !s || (e.vendor || '').toLowerCase().includes(s) || (e.notes || '').toLowerCase().includes(s) || (e.category || '').toLowerCase().includes(s)
  ).sort((a, b) => new Date(b.date) - new Date(a.date));
  EXP.page = 1; _renderExpTable();
}
function filterExpensesCat(val) {
  EXP.list = (val ? STATE.expenses.filter(e => e.category === val) : [...STATE.expenses]).sort((a, b) => new Date(b.date) - new Date(a.date));
  EXP.page = 1; _renderExpTable();
}
function filterExpensesMonth(val) {
  EXP.list = (val ? STATE.expenses.filter(e => (e.date || '').startsWith(val)) : [...STATE.expenses]).sort((a, b) => new Date(b.date) - new Date(a.date));
  EXP.page = 1; _renderExpTable();
}

function openAddExpenseModal() {
  document.getElementById('exp-edit-id').value = '';
  document.getElementById('exp-modal-title').textContent = 'Add Expense';
  document.getElementById('exp-date').value = new Date().toISOString().slice(0, 10);
  document.getElementById('exp-amount').value = '';
  document.getElementById('exp-category').value = '';
  document.getElementById('exp-method').value = 'UPI';
  document.getElementById('exp-vendor').value = '';
  document.getElementById('exp-notes').value = '';
  openModal('modal-expense');
}

function editExpense(id) {
  const exp = STATE.expenses.find(e => String(e.id) === String(id));
  if (!exp) return;
  document.getElementById('exp-edit-id').value = id;
  document.getElementById('exp-modal-title').textContent = 'Edit Expense';
  document.getElementById('exp-date').value = exp.date || '';
  document.getElementById('exp-amount').value = exp.amount || '';
  document.getElementById('exp-category').value = exp.category || '';
  document.getElementById('exp-method').value = exp.method || 'UPI';
  document.getElementById('exp-vendor').value = exp.vendor || '';
  document.getElementById('exp-notes').value = exp.notes || '';
  openModal('modal-expense');
}

function saveExpense() {
  const id = document.getElementById('exp-edit-id').value;
  const date = document.getElementById('exp-date').value;
  const amount = parseFloat(document.getElementById('exp-amount').value);
  const category = document.getElementById('exp-category').value;
  const vendor = document.getElementById('exp-vendor').value.trim();
  if (!date || !amount || !category || !vendor) { toast('⚠️ Fill all required fields', 'warning'); return; }
  const entry = {
    id: id || (Date.now() + ''), date, amount, category, vendor,
    method: document.getElementById('exp-method').value,
    notes: document.getElementById('exp-notes').value.trim(),
  };
  if (id) {
    api('api/expenses.php?id=' + id, 'PUT', entry).then(() => {
      const idx = STATE.expenses.findIndex(e => String(e.id) === id);
      if (idx > -1) STATE.expenses[idx] = entry;
      logActivity('expense_added', `Expense edited: ${vendor}`, fmt_money(amount));
      closeModal('modal-expense'); renderExpenses(); toast('✅ Expense saved', 'success');
    }).catch(e => toast('❌ ' + e.message, 'error'));
  } else {
    api('api/expenses.php', 'POST', entry).then(r => {
      if (r && r.id) entry.id = String(r.id);
      STATE.expenses.unshift(entry);
      logActivity('expense_added', `Expense added: ${vendor}`, fmt_money(amount));
      closeModal('modal-expense'); renderExpenses(); toast('✅ Expense saved', 'success');
    }).catch(e => toast('❌ ' + e.message, 'error'));
  }
}

async function deleteExpense(id) {
  const result = await Swal.fire({ title: 'Delete Expense?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Cancel', confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' } });
  if (!result.isConfirmed) return;
  api('api/expenses.php?id=' + id, 'DELETE').then(() => {
    STATE.expenses = STATE.expenses.filter(e => String(e.id) !== String(id));
    renderExpenses(); toast('🗑️ Expense deleted', 'info');
  }).catch(e => toast('❌ ' + e.message, 'error'));
}

function exportExpensesCSV() {
  const rows = [['Date', 'Category', 'Vendor', 'Method', 'Amount', 'Notes']];
  EXP.list.forEach(e => rows.push([e.date, e.category, e.vendor, e.method, e.amount, e.notes || '']));
  _downloadCSV(rows, 'expenses.csv');
}

// _downloadCSV isn't in shared-data.js (it's defined in invoices.js).
// Duplicated here as a tiny local fallback so this page doesn't
// depend on invoices.js being loaded.
if (typeof _downloadCSV !== 'function') {
  window._downloadCSV = function (rows, filename) {
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
  };
}

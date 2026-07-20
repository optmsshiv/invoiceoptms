// ============================================================
// sales.js — page-specific JS for pages/sales.php
// Depends on: common.js, app.js, sales-shared.js
// (goToNewSale() is gone — "New Sale" is now a plain link to
// sale-new.php; its default-field logic moved there as
// initNewSaleDefaults(), see sale-new.js)
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
  await bootSalesPageState();
  populateSalesListFilters();
  resetSalesFilter();
});

function exportSalesExcel() {
  const list = slFilteredSales();
  if (!list.length) { toast('⚠️ No sales to export for the selected filters', 'warning'); return; }
  const rows = [['#','Invoice No.','Invoice Date','Customer','Qty (Kg)','Net Amount','Amount Received','Outstanding','Payment Status','Status','Sales Executive','Warehouse']];
  list.forEach((s, i) => {
    const total = parseFloat(s.total)||0, recd = parseFloat(s.amount_received)||0;
    rows.push([
      i+1, s.invoice_no||'', s.sale_date||'', s.customer_name||'',
      (parseFloat(s.total_qty)||0).toFixed(2), total.toFixed(2), recd.toFixed(2), Math.max(0, total-recd).toFixed(2),
      s.payment_status||'', s.status||'Confirmed', s.sales_executive||'', s.warehouse||'Main Warehouse'
    ]);
  });
  _downloadCSV(rows, 'sales_list.csv');
  toast('✅ Exported ' + list.length + ' sales', 'success');
}


function resetSalesFilter() {
  document.getElementById('sl-f-from').value = BIZ_FROM_DATE;
  document.getElementById('sl-f-to').value = fmt_date(new Date());
  ['sl-f-customer','sl-f-warehouse','sl-f-status','sl-f-paystatus','sl-f-product','sl-f-exec'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('sl-f-invno').value = '';
  SL_PAGE = 1;
  renderSales();
}
